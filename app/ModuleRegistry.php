<?php
declare(strict_types=1);

/** Filesystem-discovered, tenant-scoped modules. Only trusted server-installed PHP is executed. */
final class ModuleRegistry {
    private const KEY_PATTERN='/^[a-z][a-z0-9-]{1,63}$/D';
    private const ROLES=['owner','admin','scheduler','accounting','contractor','client'];
    public static function installed(): array {
        $root=dirname(__DIR__).'/modules';
        if(!is_dir($root)) return [];
        $items=[];
        foreach(new DirectoryIterator($root) as $dir) {
            if(!$dir->isDir()||$dir->isDot()||$dir->isLink()) continue;
            $key=$dir->getFilename();
            if(!preg_match(self::KEY_PATTERN,$key)) continue;
            $path=$dir->getPathname();
            $manifest=$path.'/module.json';
            if(!is_file($manifest)||is_link($manifest)) continue;
            $data=json_decode((string)file_get_contents($manifest),true);
            if(!is_array($data)||($data['key']??null)!==$key) continue;
            $page=$data['page']??null;
            if(!is_string($page)||!preg_match('/^[a-zA-Z0-9_-]+\.php$/D',$page)||!is_file($path.'/'.$page)||is_link($path.'/'.$page)) continue;
            $roles=$data['roles']??self::ROLES;
            if(!is_array($roles)||!$roles||array_diff($roles,self::ROLES)) continue;
            $name=$data['name']??$key;$label=$data['menu_label']??$name;
            if(!is_string($name)||!is_string($label)||strlen($name)>120||strlen($label)>60)continue;
            $items[$key]=[
                'key'=>$key,'name'=>$name,'menu_label'=>$label,'version'=>is_string($data['version']??null)?substr($data['version'],0,30):'1.0',
                'description'=>is_string($data['description']??null)?substr($data['description'],0,500):'',
                'roles'=>$roles,'page_path'=>$path.'/'.$page
            ];
        }
        ksort($items);
        return $items;
    }
    public static function enabled(int $tenantId): array {
        $q=Database::connection()->prepare('SELECT module_key FROM tenant_modules WHERE tenant_id=? AND enabled=1');
        $q->execute([$tenantId]);
        return array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
    }
    public static function activeFor(array $tenant): array {
        $enabled=self::enabled((int)$tenant['tenant_id']);$result=[];
        foreach(self::installed() as $key=>$module) {
            if(isset($enabled[$key])&&in_array((string)$tenant['role'],$module['roles'],true))$result[$key]=$module;
        }
        return $result;
    }
}
