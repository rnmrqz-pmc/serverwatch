<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Server extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ip',
        'role',
        'env',
        'db_type',
        'db_host',
        'db_port',
        'db_user',
        'db_password',
        'db_name',
        'ssh_user',
        'ssh_port',
        'ssh_password',
    ];

    /**
     * db_password and ssh_password are stored AES-256 encrypted in the database.
     * Laravel's `encrypted` cast handles encryption/decryption transparently.
     * The value is NEVER serialised to JSON responses (see $hidden).
     */
    protected $casts = [
        'db_port'      => 'integer',
        'db_password'  => 'encrypted',
        'ssh_port'     => 'integer',
        'ssh_password' => 'encrypted',
    ];

    /**
     * Fields that must never appear in API JSON responses.
     */
    protected $hidden = ['db_password', 'ssh_password'];
}
