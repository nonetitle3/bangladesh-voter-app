<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VoterRecord extends Model {
 protected $table='voter_records';
 protected $guarded=[];
 public function document(){ return $this->belongsTo(Document::class); }
}