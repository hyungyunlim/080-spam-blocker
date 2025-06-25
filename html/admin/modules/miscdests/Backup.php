<?php
namespace FreePBX\modules\Miscdests;
use FreePBX\modules\Backup as Base;
class Backup Extends Base\BackupBase{
  public function runBackup($id,$transaction){
    $configs = $this->FreePBX->Miscdests->mdlist(true);
    $this->addConfigs($configs);
  }
}