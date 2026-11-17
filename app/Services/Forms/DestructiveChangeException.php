<?php

namespace App\Services\Forms;

use RuntimeException;

/** Perubahan akan merusak jawaban yang sudah masuk — pembuat form harus mengonfirmasi dulu. */
class DestructiveChangeException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Perubahan ini bisa merusak jawaban yang sudah masuk.');
    }
}
