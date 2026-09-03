<?php

namespace Extensions\Modules\Tickets\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Extensions\Modules\Tickets\Models\TicketDepartment;

class TicketDepartmentsController extends Controller
{
    public function index()
    {
        return admin_view('tickets::ticket-departments.index');
    }

    public function create()
    {
        return admin_view('tickets::ticket-departments.create');
    }

    public function edit(TicketDepartment $department)
    {
        return admin_view('tickets::ticket-departments.edit', [
            'department' => $department,
        ]);
    }
}
