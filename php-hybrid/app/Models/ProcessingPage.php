<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ProcessingPage extends Model {
 protected $table='processing_pages';
 protected $guarded=[];
 protected $casts=['ocr_used'=>'boolean','started_at'=>'datetime','completed_at'=>'datetime'];
 public function document(){ return $this->belongsTo(Document::class); }
}