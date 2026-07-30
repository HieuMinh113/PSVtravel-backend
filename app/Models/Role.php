<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    // 3 vai trò cố định của hệ thống — dùng hằng số để tránh gõ sai chuỗi ở nhiều nơi
    public const ADMIN = 'admin';
    public const STAFF = 'staff';
    public const CUSTOMER = 'customer';

    protected $fillable = ['name', 'label'];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}