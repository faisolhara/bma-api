<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class TenantMaster extends Model
{
    protected $table       = 'bima.tenant_master';
    public $timestamps     = false;

    protected $primaryKey  = 'tenant_id';

    const TENANT   = 'TENANT';
    const LANDLORD = 'LANDLORD';

}
