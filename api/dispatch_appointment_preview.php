<?php
declare(strict_types=1);

/**
 * Cycle 64: read-only dispatch preview for an existing appointment.
 * No assignment, reservation, status change or Marketplace mutation.
 */
require_once dirname(__DIR__).'/app/bootstrap.php';
require_once dirname(__DIR__).'/app/ContractorEligibilityEngine.php';
require_once dirname(__DIR__).'/app/ContractorRankingEngine.php';
require_once dirname(__DIR__).'/app/ContractorAssignmentValidator.php';
require_once dirname(__DIR__).'/app/ContractorScheduleValidator.php';

Auth::requireUser();
$tenant=Auth::requireRole('owner','admin','scheduler');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){
 header('Allow: GET');
 json_response(['error'=>'Method not allowed'],405);
}
$id=filter_var($_GET['appointment_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id)json_response(['error'=>'Valid appointment ID required'],422);
$tenantId=(int)$tenant['tenant_id'];
$pdo=Database::connection();
try{
 $q=$pdo->prepare('SELECT id,title,status,starts_at,ends_at,service_id,client_location_id FROM appointments WHERE id=? AND tenant_id=? LIMIT 1');
 $q->execute([(int)$id,$tenantId]);
 $appointment=$q->fetch(PDO::FETCH_ASSOC);
 if(!$appointment)json_response(['error'=>'Appointment not found'],404);
 if(!in_array($appointment['status'],['tentative','scheduled','confirmed'],true)){
  json_response(['error'=>'Appointment status is not eligible for dispatch preview'],409);
 }
 if($appointment['client_location_id']===null){
  json_response(['error'=>'Select a client location before requesting dispatch recommendations'],422);
 }
 $locationId=(int)$appointment['client_location_id'];
 $serviceId=$appointment['service_id']===null?null:(int)$appointment['service_id'];
 $contractors=ContractorEligibilityEngine::evaluate(
  $pdo,$tenantId,$locationId,$appointment['starts_at'],$appointment['ends_at'],(int)$id,$serviceId
 );
 foreach($contractors as &$candidate){
  $validation=ContractorAssignmentValidator::evaluate($pdo,$tenantId,(int)$candidate['contractor_id'],$serviceId,$locationId);
  // Mirror the scheduling checks used by the appointment assignment path.
  // Advisory only: writes must revalidate under contractor locks.
  $scheduleErrors=[];
  try{
   check_contractor_availability($pdo,$tenantId,[(int)$candidate['contractor_id']],$appointment['starts_at'],$appointment['ends_at']);
  }catch(RuntimeException $e){
   $scheduleErrors[]=$e->getMessage();
  }
  try{
   check_contractor_conflicts($pdo,$tenantId,[(int)$candidate['contractor_id']],$appointment['starts_at'],$appointment['ends_at'],(int)$id);
  }catch(RuntimeException $e){
   $scheduleErrors[]=$e->getMessage();
  }
  $candidate['schedule_policy']=['allowed'=>count($scheduleErrors)===0,'errors'=>$scheduleErrors];
  $candidate['assignment_policy']=$validation;
  $candidate['eligible_for_review']=(
   !empty($candidate['eligible_for_review']) && !empty($validation['allowed']) && count($scheduleErrors)===0
  );
  $candidate['assignment_approved']=false;
 }
 unset($candidate);
 $contractors=ContractorRankingEngine::rank($pdo,$tenantId,$appointment['starts_at'],$contractors,(int)$id);
 $q=$pdo->prepare('SELECT id FROM appointment_bid_listings WHERE tenant_id=? AND appointment_id=? LIMIT 1');
 $q->execute([$tenantId,(int)$id]);
 $marketplace=(bool)$q->fetchColumn();
 json_response([
  'ok'=>true,
  'appointment'=>[
   'id'=>(int)$id,
   'title'=>$appointment['title'],
   'status'=>$appointment['status'],
   'starts_at'=>$appointment['starts_at'],
   'ends_at'=>$appointment['ends_at'],
   'service_id'=>$serviceId,
   'client_location_id'=>$locationId
  ],
  'marketplace_listing_exists'=>$marketplace,
  'contractors'=>$contractors,
  'count'=>count($contractors),
  'eligible_for_review_count'=>count(array_filter($contractors,static fn(array $c):bool=>!empty($c['eligible_for_review']))),
  'read_only'=>true,
  'automatic_assignment'=>false,
  'note'=>'Advisory preview only. Revalidate all assignment constraints immediately before any owner-confirmed action.'
 ]);
}catch(InvalidArgumentException $e){
 json_response(['error'=>'Invalid dispatch preview request'],422);
}catch(Throwable $e){
 error_log('Dispatch preview: '.$e->getMessage());
 json_response(['error'=>'Unable to generate dispatch preview'],500);
}
