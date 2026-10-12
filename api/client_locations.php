<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/Geocoding.php';
$user = Auth::requireUser();
$tenant = Auth::tenant();
$tid = (int)$tenant['tenant_id'];
$role = (string)$tenant['role'];
$pdo = Database::connection();
$managers = ['owner','admin','scheduler'];
if (!in_array($role, array_merge($managers,['client']), true)) json_response(['error'=>'Forbidden'],403);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'GET') verify_csrf();
$d = $method === 'GET' ? $_GET : request_data();
$clientId = (int)($d['client_id'] ?? 0);
if ($role === 'client') {
    $q=$pdo->prepare('SELECT id FROM clients WHERE tenant_id=? AND user_id=? LIMIT 1');
    $q->execute([$tid,(int)$user['id']]);
    $clientId=(int)($q->fetchColumn() ?: 0);
}
if ($clientId < 1) json_response(['error'=>'Client not found'],404);
$q=$pdo->prepare('SELECT id FROM clients WHERE id=? AND tenant_id=?');
$q->execute([$clientId,$tid]);
if (!$q->fetchColumn()) json_response(['error'=>'Client not found'],404);
if ($method === 'GET') {
    $q=$pdo->prepare('SELECT id,client_id,label,address1,address2,city,state,postal_code,service_instructions,latitude,longitude,geocoding_status,geocoding_attempted_at,geocoding_resolved_at,is_primary FROM client_locations WHERE tenant_id=? AND client_id=? ORDER BY is_primary DESC,label,id');
    $q->execute([$tid,$clientId]);
    json_response(['locations'=>$q->fetchAll()]);
}
if (!in_array($method,['POST','PUT','DELETE'],true)) json_response(['error'=>'Method not allowed'],405);
$id=(int)($d['id'] ?? 0);
if ($method !== 'POST' && $id < 1) json_response(['error'=>'Invalid location ID'],422);
if ($method !== 'DELETE') {
    $fields=[];
    foreach (['label'=>120,'address1'=>190,'address2'=>190,'city'=>100,'state'=>100,'postal_code'=>30,'service_instructions'=>5000] as $key=>$max) {
        $v=trim((string)($d[$key] ?? ''));
        if (mb_strlen($v)>$max) json_response(['error'=>"$key is too long"],422);
        if (in_array($key,['label','address1','city','state','postal_code'],true) && $v==='') json_response(['error'=>"$key is required"],422);
        $fields[$key]=$v === '' ? null : $v;
    }
    $primary=!empty($d['is_primary']) ? 1 : 0;
}
try {
    $shouldGeocode = false;
    $pdo->beginTransaction();
    // Serialize modifications to a client's primary-location selection.
    $lock=$pdo->prepare('SELECT id FROM clients WHERE id=? AND tenant_id=? FOR UPDATE');
    $lock->execute([$clientId,$tid]);
    if (!$lock->fetchColumn()) { $pdo->rollBack(); json_response(['error'=>'Client not found'],404); }
    if ($method !== 'POST') {
        $q=$pdo->prepare('SELECT id,is_primary,address1,address2,city,state,postal_code FROM client_locations WHERE id=? AND client_id=? AND tenant_id=? FOR UPDATE');
        $q->execute([$id,$clientId,$tid]);
        $existing=$q->fetch();
        if (!$existing) { $pdo->rollBack(); json_response(['error'=>'Location not found'],404); }
    }
    if ($method === 'DELETE') {
        $q=$pdo->prepare('DELETE FROM client_locations WHERE id=? AND tenant_id=? AND client_id=?');
        $q->execute([$id,$tid,$clientId]);
    } else {
        $count=$pdo->prepare('SELECT COUNT(*) FROM client_locations WHERE tenant_id=? AND client_id=?');
        $count->execute([$tid,$clientId]);
        if ($method==='POST' && (int)$count->fetchColumn()===0) $primary=1;
        if ($primary) {
            $q=$pdo->prepare('UPDATE client_locations SET is_primary=0 WHERE tenant_id=? AND client_id=? AND is_primary=1');
            $q->execute([$tid,$clientId]);
        }
        $values=[$fields['label'],$fields['address1'],$fields['address2'],$fields['city'],$fields['state'],$fields['postal_code'],$fields['service_instructions'],$primary];
        if ($method==='POST') {
            $q=$pdo->prepare('INSERT INTO client_locations (label,address1,address2,city,state,postal_code,service_instructions,is_primary,tenant_id,client_id) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $q->execute([...$values,$tid,$clientId]);
            $id=(int)$pdo->lastInsertId();
            $shouldGeocode = true;
        } else {
            // Coordinates remain valid only while the address is unchanged.
            $addressChanged = false;

            foreach (['address1','address2','city','state','postal_code'] as $key) {
                if (
                    (string)($existing[$key] ?? '') !==
                    (string)($fields[$key] ?? '')
                ) {
                    $addressChanged = true;
                    break;
                }
            }

            if ($addressChanged) {
                $shouldGeocode = true;
                $q=$pdo->prepare(
                    'UPDATE client_locations
                     SET label=?,address1=?,address2=?,city=?,state=?,
                         postal_code=?,service_instructions=?,is_primary=?,
                         latitude=NULL,longitude=NULL,
                         geocoding_status=\'pending\',
                         geocoding_attempted_at=NULL,
                         geocoding_resolved_at=NULL
                     WHERE id=? AND tenant_id=? AND client_id=?'
                );
            } else {
                $q=$pdo->prepare(
                    'UPDATE client_locations
                     SET label=?,address1=?,address2=?,city=?,state=?,
                         postal_code=?,service_instructions=?,is_primary=?
                     WHERE id=? AND tenant_id=? AND client_id=?'
                );
            }

            $q->execute([...$values,$id,$tid,$clientId]);
        }
    }
    // Ensure a remaining location is primary, even when deleting or unsetting it.
    $q=$pdo->prepare('SELECT id FROM client_locations WHERE tenant_id=? AND client_id=? ORDER BY is_primary DESC,id LIMIT 1');
    $q->execute([$tid,$clientId]);
    $first=$q->fetchColumn();
    if ($first) {
        $q=$pdo->prepare('UPDATE client_locations SET is_primary=1 WHERE id=? AND NOT EXISTS (SELECT 1 FROM (SELECT id FROM client_locations WHERE tenant_id=? AND client_id=? AND is_primary=1 LIMIT 1) p)');
        $q->execute([(int)$first,$tid,$clientId]);
    }
    $pdo->commit();

    // External geocoding must never run inside the location transaction.
    if ($method !== 'DELETE' && $shouldGeocode) {
        try {
            $result = Geocoding::resolve($pdo, $fields);

            $status = (string)($result['status'] ?? 'unavailable');

            if (!in_array(
                $status,
                ['resolved','rejected','not_found','unavailable'],
                true
            )) {
                $status = 'unavailable';
            }

            if (
                $status === 'resolved' &&
                isset($result['latitude'], $result['longitude'])
            ) {
                // Assign coordinates only if the address is still current.
                $q = $pdo->prepare(
                    'UPDATE client_locations
                     SET latitude=?,longitude=?,
                         geocoding_status=\'resolved\',
                         geocoding_attempted_at=UTC_TIMESTAMP(6),
                         geocoding_resolved_at=UTC_TIMESTAMP(6)
                     WHERE id=? AND tenant_id=? AND client_id=?
                       AND address1=?
                       AND city=?
                       AND state=?
                       AND postal_code=?
                       AND address2 <=> ?
                       AND latitude IS NULL
                       AND longitude IS NULL
                       AND geocoding_status=\'pending\'
                       AND geocoding_attempted_at IS NULL'
                );

                $q->execute([
                    $result['latitude'],
                    $result['longitude'],
                    $id,
                    $tid,
                    $clientId,
                    $fields['address1'],
                    $fields['city'],
                    $fields['state'],
                    $fields['postal_code'],
                    $fields['address2']
                ]);
            } else {
                // Record unsuccessful attempts only if the same address
                // still has no coordinates.
                $q = $pdo->prepare(
                    'UPDATE client_locations
                     SET geocoding_status=?,
                         geocoding_attempted_at=UTC_TIMESTAMP(6),
                         geocoding_resolved_at=NULL
                     WHERE id=? AND tenant_id=? AND client_id=?
                       AND address1=? AND city=? AND state=?
                       AND postal_code=? AND address2 <=> ?
                       AND latitude IS NULL AND longitude IS NULL
                       AND geocoding_status=\'pending\'
                       AND geocoding_attempted_at IS NULL'
                );

                $q->execute([
                    $status,
                    $id,
                    $tid,
                    $clientId,
                    $fields['address1'],
                    $fields['city'],
                    $fields['state'],
                    $fields['postal_code'],
                    $fields['address2']
                ]);
            }
        } catch (Throwable $geocodingError) {
            // A provider failure must not invalidate a saved location.
            // Preserve the address and record a retryable failure.
            try {
                $statusUpdate = $pdo->prepare(
                    'UPDATE client_locations
                     SET geocoding_status=?,
                         geocoding_attempted_at=UTC_TIMESTAMP(6),
                         geocoding_resolved_at=NULL
                     WHERE id=? AND tenant_id=? AND client_id=?
                       AND address1=? AND city=? AND state=?
                       AND postal_code=? AND address2 <=> ?
                       AND latitude IS NULL AND longitude IS NULL
                       AND geocoding_status=\'pending\'
                       AND geocoding_attempted_at IS NULL'
                );

                $statusUpdate->execute([
                    'unavailable',
                    $id,
                    $tid,
                    $clientId,
                    $fields['address1'],
                    $fields['city'],
                    $fields['state'],
                    $fields['postal_code'],
                    $fields['address2']
                ]);
            } catch (Throwable $statusError) {
                // Status persistence must not invalidate the saved location.
                error_log(
                    'Client location geocoding status update failed: ' .
                    get_class($statusError)
                );
            }
            error_log(
                'Client location geocoding failed: ' .
                get_class($geocodingError)
            );
        }
    }

    json_response(['ok'=>true,'id'=>$id],$method==='POST'?201:200);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Client locations error: '.$e->getMessage());
    json_response(['error'=>'Unable to save location'],500);
}
