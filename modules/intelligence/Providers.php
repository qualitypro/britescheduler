<?php
declare(strict_types=1);
/**
 * BI provider contract: an enabled module may ship intelligence-metrics.json
 * containing {"metrics":[{"key":"orders","name":"Orders","table":"module_slug_orders",
 * "date":"created_at","aggregation":"count","column":"id"}]}.
 * SQL identifiers are strictly validated; expressions supplied by modules are never executed.
 */
final class IntelligenceProviders {
    public static function discover(PDO $pdo,array $tenant): array {
        $result=[];
        foreach(ModuleRegistry::activeFor($tenant) as $key=>$module){
            if($key==='intelligence')continue;
            $path=dirname($module['page_path']).'/intelligence-metrics.json';
            if(!is_file($path)||is_link($path)||filesize($path)>65536)continue;
            $data=json_decode((string)file_get_contents($path),true);
            if(!is_array($data)||!is_array($data['metrics']??null))continue;
            foreach(array_slice($data['metrics'],0,30) as $item){
                if(!is_array($item))continue;
                $metric=(string)($item['key']??'');
                $table=(string)($item['table']??'');
                $date=(string)($item['date']??'');
                $column=(string)($item['column']??'');
                $agg=(string)($item['aggregation']??'');
                $name=(string)($item['name']??'');
                $prefix=str_replace('-','_',$key).'_';
                if(!preg_match('/^[a-z][a-z0-9_]{1,50}$/D',$metric)||
                   !preg_match('/^[a-z][a-z0-9_]*$/D',$table)||
                   !str_starts_with($table,$prefix)||
                   !preg_match('/^[a-z][a-z0-9_]*$/D',$date)||
                   !preg_match('/^[a-z][a-z0-9_]*$/D',$column)||
                   !in_array($agg,['count','sum','avg'],true)||
                   $name===''||strlen($name)>100)continue;
                $q=$pdo->prepare('SELECT COLUMN_NAME,DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');
                $q->execute([$table]);$cols=$q->fetchAll(PDO::FETCH_KEY_PAIR);
                if(!isset($cols['tenant_id'],$cols[$date],$cols[$column]))continue;
                if(!in_array($cols[$date],['date','datetime','timestamp'],true))continue;
                if($agg!=='count'&&!in_array($cols[$column],['int','bigint','smallint','tinyint','decimal','float','double','mediumint'],true))continue;
                $expr=match($agg){'count'=>'COUNT(*)','sum'=>"SUM(`$column`)",'avg'=>"AVG(`$column`)"};
                $result[$key.'.'.$metric]=[
                    'name'=>$name,'source'=>$module['name'],'table'=>$table,
                    'date'=>$date,'expr'=>$expr
                ];
            }
        }
        return $result;
    }
}
