<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable {
 protected $table='users';
 protected $guarded=[];
 protected $hidden=['password'];
 protected $casts=['is_active'=>'boolean','created_at'=>'datetime','updated_at'=>'datetime'];
 public function voterRecords(){ return $this->hasMany(VoterRecord::class,'created_by'); }
}