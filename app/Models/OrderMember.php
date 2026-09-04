<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMember extends Model
{
    protected $table = 'order_members';

    protected $fillable = [
        'order_id',
        'user_id',
        'email',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getEmailAttribute($value)
    {
        if ($this->user) {
            return $this->user->email;
        }

        return $value;
    }

    public function sendEmailNotification()
    {
        $payload = [
            'mailable_type' => Order::class,
            'mailable_id' => $this->order_id,
            'identifier' => 'order.member.invited',
            'to' => $this->email,
            'variables' => [
                'package_name' => $this->order->package->name,
                'member_email' => $this->email,
            ],
            'button_url' => route('dashboard.order-invites'),
        ];

        if ($this->user_id && $this->user) {
            $this->user->email([
                ...$payload,
                'button' => [
                    'url' => $payload['button_url'],
                ],
            ]);

            return;
        }

        Email::actions()->sendEmailToAddress($payload);
    }

    public function sendAcceptionEmailNotification($user)
    {
        $this->order->user->email([
            'mailable_type' => Order::class,
            'mailable_id' => $this->order_id,
            'identifier' => 'order.member.accepted',
            'variables' => [
                'package_name' => $this->order->package->name,
                'username' => $user->username,
            ],
            'button' => [
                'url' => route('orders.view.members', ['order' => $this->order_id]),
            ],
        ]);
    }
}
