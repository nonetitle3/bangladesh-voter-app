<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class UploadBatch extends Model {
 protected $table='upload_batches';
 public $incrementing=false;
 protected $keyType='string';
 protected $guarded=[];
 public function documents(){ return $this->hasMany(Document::class,'upload_batch_id'); }
}
