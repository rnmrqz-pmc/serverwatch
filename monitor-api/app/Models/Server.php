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
        'cpu_threshold_info',
        'cpu_threshold_warning',
        'cpu_threshold_critical',
        'ram_threshold_info',
        'ram_threshold_warning',
        'ram_threshold_critical',
        'disk_threshold_info',
        'disk_threshold_warning',
        'disk_threshold_critical',
    ];

    /**
     * db_password and ssh_password are stored AES-256 encrypted in the database.
     * Laravel's `encrypted` cast handles encryption/decryption transparently.
     * The value is NEVER serialised to JSON responses (see $hidden).
     */
    protected $casts = [
        'db_port'                 => 'integer',
        'db_password'             => 'encrypted',
        'ssh_port'                => 'integer',
        'ssh_password'            => 'encrypted',
        'cpu_threshold_info'      => 'integer',
        'cpu_threshold_warning'   => 'integer',
        'cpu_threshold_critical'  => 'integer',
        'ram_threshold_info'      => 'integer',
        'ram_threshold_warning'   => 'integer',
        'ram_threshold_critical'  => 'integer',
        'disk_threshold_info'     => 'integer',
        'disk_threshold_warning'  => 'integer',
        'disk_threshold_critical' => 'integer',
    ];

    /**
     * Fields that must never appear in API JSON responses.
     */
    protected $hidden = ['db_password', 'ssh_password'];
}
