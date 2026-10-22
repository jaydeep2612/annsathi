<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ParcelQrCode extends Model
{
    use SoftDeletes;

    protected $fillable = ['restaurant_id', 'name', 'qr_token', 'qr_path', 'is_active'];

    public function restaurant() { return $this->belongsTo(Restaurant::class); }
    public function sessions() { return $this->hasMany(ParcelQrSession::class); }
}