<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\CustomerMail;
use App\Models\Email;
use App\Models\EmailTemplate;

class EmailsController extends Controller
{
    public function index()
    {
        return view('admin::emails.index');
    }

    public function view(Email $email)
    {
        return new CustomerMail($email);
    }

    public function configure()
    {
        return view('admin::emails.configure');
    }

    public function templates()
    {
        return view('admin::emails.templates.index');
    }

    public function editTemplate(string $template)
    {
        abort_unless(EmailTemplate::definitionExists($template), 404);

        return view('admin::emails.templates.edit', [
            'template' => $template,
        ]);
    }
}
