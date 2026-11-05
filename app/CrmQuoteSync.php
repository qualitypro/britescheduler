<?php
declare(strict_types=1);
/** Called inside the existing quote transaction; never opens or commits its own. */
final class CrmQuoteSync {
 public static function sync(PDO $db,int $tenantId,int $requestId,int $actorId):void {
  // CRM is opt-in. Skip safely until both migrations have been applied.
  $q=$db->prepare("SELECT 1 FROM tenant_modules WHERE tenant_id=? AND module_key='crm' AND enabled=1");
  $q->execute([$tenantId]);if(!$q->fetchColumn())return;
  $q=$db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('crm_quote_links','crm_opportunities','crm_activities')");
  $q->execute();if((int)$q->fetchColumn()!==3)return;
  $q=$db->prepare('SELECT r.id,r.client_id,r.description,r.status,r.service_id,s.name service_name FROM contractor_quote_requests r LEFT JOIN services s ON s.id=r.service_id AND s.tenant_id=r.tenant_id WHERE r.tenant_id=? AND r.id=?');
  $q->execute([$tenantId,$requestId]);$request=$q->fetch(PDO::FETCH_ASSOC);
  if(!$request)return;
  $q=$db->prepare('SELECT id,amount FROM contractor_quote_offers WHERE tenant_id=? AND request_id=? ORDER BY revision DESC,id DESC LIMIT 1');
  $q->execute([$tenantId,$requestId]);$offer=$q->fetch(PDO::FETCH_ASSOC)?:null;
  $q=$db->prepare('SELECT opportunity_id,last_status,last_offer_id FROM crm_quote_links WHERE tenant_id=? AND request_id=? FOR UPDATE');
  $q->execute([$tenantId,$requestId]);$link=$q->fetch(PDO::FETCH_ASSOC)?:null;
  $status=(string)$request['status'];
  $stage=match($status){'quoted'=>'quoted','accepted'=>'won','declined','cancelled'=>'lost',default=>'new'};
  $offerId=$offer?(int)$offer['id']:null;
  $amount=$offer?(string)$offer['amount']:'0';
  if(!$link){
   $title=substr('Quote #'.$requestId.' · '.($request['service_name']?:'Service request'),0,190);
   $q=$db->prepare('INSERT INTO crm_opportunities(tenant_id,title,client_id,stage,estimated_value,notes,created_by) VALUES(?,?,?,?,?,?,?)');
   $q->execute([$tenantId,$title,(int)$request['client_id'],$stage,$amount,substr((string)$request['description'],0,5000),$actorId]);
   $opportunityId=(int)$db->lastInsertId();
   $q=$db->prepare('INSERT INTO crm_quote_links(tenant_id,request_id,opportunity_id,last_status,last_offer_id) VALUES(?,?,?,?,?)');
   $q->execute([$tenantId,$requestId,$opportunityId,$status,$offerId]);
   $changed=true;
  }else{
   $opportunityId=(int)$link['opportunity_id'];
   $changed=$link['last_status']!==$status||($link['last_offer_id']===null?null:(int)$link['last_offer_id'])!==$offerId;
   if(!$changed)return;
   $q=$db->prepare('UPDATE crm_opportunities SET stage=?,estimated_value=? WHERE tenant_id=? AND id=?');
   $q->execute([$stage,$amount,$tenantId,$opportunityId]);
   $q=$db->prepare('UPDATE crm_quote_links SET last_status=?,last_offer_id=? WHERE tenant_id=? AND request_id=?');
   $q->execute([$status,$offerId,$tenantId,$requestId]);
  }
  if($changed){
   $body=substr('Quote request #'.$requestId.' status: '.str_replace('_',' ',$status).($offer?' · offer $'.$amount:''),0,5000);
   $q=$db->prepare("INSERT INTO crm_activities(tenant_id,client_id,opportunity_id,kind,body,created_by) VALUES(?,?,?,'note',?,?)");
   $q->execute([$tenantId,(int)$request['client_id'],$opportunityId,$body,$actorId]);
  }
 }
}
