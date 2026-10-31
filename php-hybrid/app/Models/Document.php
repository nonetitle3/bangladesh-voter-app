<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Document extends Model {
 protected $table='documents';
 protected $guarded=[];
 protected $casts=['ocr_used'=>'boolean','uploaded_at'=>'datetime'];
 public function voterRecords(){ return $this->hasMany(VoterRecord::class); }
 public function pages(){ return $this->hasMany(ProcessingPage::class); }
}