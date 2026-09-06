<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MassMail;

class MassMailsController extends Controller
{
    public function index()
    {
        return view('admin::emails.mass-mails.index');
    }

    public function create()
    {
        return view('admin::emails.mass-mails.create');
    }

    public function show(MassMail $massMail)
    {
        return view('admin::emails.mass-mails.show', [
            'massMail' => $massMail,
        ]);
    }
}
