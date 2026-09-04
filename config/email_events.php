<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Account
    |--------------------------------------------------------------------------
    */

    'email_verification' => [
        'name' => 'Email verification',
        'group' => 'Account',
        'description' => 'Sent when a user needs to verify their email address.',
        'subject' => 'Verify your email address',
        'body' => <<<'BODY'
Thanks for signing up! Please click the button below to verify your email address. If you did not create an account, no further action is required.
BODY,
        'button_text' => 'Verify Email Address',
        'placeholders' => [],
    ],

    'password_reset' => [
        'name' => 'Password reset request',
        'group' => 'Account',
        'description' => 'Sent when a user requests a password reset link.',
        'subject' => 'Password Reset Request',
        'body' => <<<'BODY'
You are receiving this email because we received a password reset request for your account.
If you did not request a password reset, no further action is required.
BODY,
        'button_text' => 'Reset Password',
        'placeholders' => [],
    ],

    'account.created' => [
        'name' => 'Account created',
        'group' => 'Account',
        'description' => 'Sent when an administrator creates an account with a generated password.',
        'subject' => 'Your account has been created on {{app_name}}',
        'body' => <<<'BODY'
You are receiving this email because your account has been created on {{app_name}}.
Please change your password after logging in.
**Account Details:**
BODY,
        'button_text' => 'Login to your account',
        'placeholders' => [
            'app_name' => 'Application name',
        ],
    ],

    'account.activated' => [
        'name' => 'Account activated',
        'group' => 'Account',
        'description' => 'Sent when an administrator activates a user account.',
        'subject' => 'Your account has been activated',
        'body' => <<<'BODY'
You are receiving this email because your account on {{app_name}} is now active.
You can now log in to your account.
BODY,
        'button_text' => 'Login to your account',
        'placeholders' => [
            'app_name' => 'Application name',
        ],
    ],

    'account.new-login' => [
        'name' => 'New login',
        'group' => 'Account',
        'description' => 'Sent after a successful login.',
        'subject' => 'New login to your account',
        'body' => <<<'BODY'
You are receiving this email because there was a new login to your account on {{app_name}}.
If this was you, you can safely ignore this email.
BODY,
        'button_text' => null,
        'placeholders' => [
            'app_name' => 'Application name',
        ],
    ],

    'account.password.reset' => [
        'name' => 'Password was reset',
        'group' => 'Account',
        'description' => 'Sent after a user successfully resets their password.',
        'subject' => 'Your password has been reset',
        'body' => <<<'BODY'
You are receiving this email because your password was reset on {{app_name}}.
If you did not make this change, please contact support immediately.
BODY,
        'button_text' => null,
        'placeholders' => [
            'app_name' => 'Application name',
        ],
    ],

    'account.password.change.confirmed' => [
        'name' => 'Password changed',
        'group' => 'Account',
        'description' => 'Sent after a user changes their password from account settings.',
        'subject' => 'Your password has been changed',
        'body' => <<<'BODY'
You are receiving this email because your password was changed of your account on {{app_name}}.
If you did not make this change, please contact support immediately.
BODY,
        'button_text' => null,
        'placeholders' => [
            'app_name' => 'Application name',
        ],
    ],

    'account.2fa.enabled' => [
        'name' => 'Two-factor authentication enabled',
        'group' => 'Account',
        'description' => 'Sent when a user enables two-factor authentication.',
        'subject' => 'Two-factor authentication enabled',
        'body' => <<<'BODY'
You are receiving this email because two-factor authentication was enabled on your account on {{app_name}}.
If you did not make this change, please contact support immediately.
BODY,
        'button_text' => null,
        'placeholders' => [
            'app_name' => 'Application name',
        ],
    ],

    'account.2fa.disabled' => [
        'name' => 'Two-factor authentication disabled',
        'group' => 'Account',
        'description' => 'Sent when a user disables two-factor authentication.',
        'subject' => 'Two-factor authentication disabled',
        'body' => <<<'BODY'
You are receiving this email because two-factor authentication was disabled on your account on {{app_name}}.
If you did not make this change, please contact support immediately.
BODY,
        'button_text' => null,
        'placeholders' => [
            'app_name' => 'Application name',
        ],
    ],

    'account.2fa.disabled_by_admin' => [
        'name' => 'Two-factor authentication disabled by admin',
        'group' => 'Account',
        'description' => 'Sent when an administrator disables two-factor authentication for a user.',
        'subject' => 'Two-factor authentication has been disabled by an administrator',
        'body' => <<<'BODY'
You are receiving this email because two-factor authentication was disabled on your account on {{app_name}}.
This action was performed by an administrator.
If you did not request this change, please contact support immediately.
BODY,
        'button_text' => null,
        'placeholders' => [
            'app_name' => 'Application name',
        ],
    ],

    'account.2fa.disable.request' => [
        'name' => 'Two-factor authentication disable request',
        'group' => 'Account',
        'description' => 'Sent when a user requests to disable two-factor authentication via email.',
        'subject' => 'Two-factor authentication disable request',
        'body' => <<<'BODY'
You are receiving this email because a request to disable two-factor authentication was made on your account on {{app_name}}.
If you did not make this request, please contact support immediately.
Click the button below to disable two-factor authentication.
BODY,
        'button_text' => 'Disable Two-Factor Authentication',
        'placeholders' => [
            'app_name' => 'Application name',
        ],
    ],

    'account.2fa.disable.confirmed' => [
        'name' => 'Two-factor authentication disable confirmed',
        'group' => 'Account',
        'description' => 'Sent after two-factor authentication is disabled via the email confirmation link.',
        'subject' => 'Two-Factor Authentication Disabled',
        'body' => <<<'BODY'
You are receiving this email because two-factor authentication (2FA) has been disabled on your account on {{app_name}}.
If you did not make this change, please contact support immediately.
BODY,
        'button_text' => null,
        'placeholders' => [
            'app_name' => 'Application name',
        ],
    ],

    'account.email.change.requested' => [
        'name' => 'Email change confirmation',
        'group' => 'Account',
        'description' => 'Sent to the new address when a user requests to change their email.',
        'subject' => 'Confirm your new email address',
        'body' => <<<'BODY'
You are receiving this email because you requested to change your email address on {{app_name}}.
Please click the button below to confirm your new email address.
If you did not request this change, please ignore this email.
BODY,
        'button_text' => 'Confirm New Email Address',
        'placeholders' => [
            'app_name' => 'Application name',
        ],
    ],

    'account.email.change.confirmed' => [
        'name' => 'Email address changed',
        'group' => 'Account',
        'description' => 'Sent to the previous address after an email change is confirmed.',
        'subject' => 'Your email address has been changed',
        'body' => <<<'BODY'
You are receiving this email because your email address was changed of your account on {{app_name}}.
The new email address is **{{new_email}}**.
If you did not make this change, please contact support immediately.
BODY,
        'button_text' => null,
        'placeholders' => [
            'app_name' => 'Application name',
            'new_email' => 'The new email address',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Orders
    |--------------------------------------------------------------------------
    */

    'order.confirmation' => [
        'name' => 'Order confirmation',
        'group' => 'Orders',
        'description' => 'Sent when a new order is received.',
        'subject' => 'Order Confirmation for {{package_name}}',
        'body' => <<<'BODY'
You are receiving this email because your order has been successfully received.
Your order is currently being processed, view the status of your order with the button below.
**Order Details:**
BODY,
        'button_text' => 'View Order',
        'placeholders' => [
            'package_name' => 'Package name',
            'order_id' => 'Order ID',
            'cycle' => 'Price and billing cycle',
            'status' => 'Order status',
            'due_date' => 'Due date',
        ],
    ],

    'order.activated' => [
        'name' => 'Order activated',
        'group' => 'Orders',
        'description' => 'Sent when an order is activated.',
        'subject' => 'Your order has been activated - {{package_name}} #{{order_id}}',
        'body' => <<<'BODY'
You are receiving this email because your order has been activated.
Your service is now active, and you can start using it immediately.
**Order Details:**
BODY,
        'button_text' => 'View Order',
        'placeholders' => [
            'package_name' => 'Package name',
            'order_id' => 'Order ID',
            'cycle' => 'Price and billing cycle',
            'status' => 'Order status',
            'due_date' => 'Due date',
        ],
    ],

    'order.suspended' => [
        'name' => 'Order suspended',
        'group' => 'Orders',
        'description' => 'Sent when an order is suspended.',
        'subject' => 'Your order has been suspended - {{package_name}} #{{order_id}}',
        'body' => <<<'BODY'
You are receiving this email because your order has been suspended.
Please renew your order to avoid service interruption using the button below.
If no action is taken, your order will be terminated permanently.
**Order Details:**
BODY,
        'button_text' => 'View Order',
        'placeholders' => [
            'package_name' => 'Package name',
            'order_id' => 'Order ID',
            'cycle' => 'Price and billing cycle',
            'status' => 'Order status',
            'due_date' => 'Due date',
        ],
    ],

    'order.unsuspended' => [
        'name' => 'Order unsuspended',
        'group' => 'Orders',
        'description' => 'Sent when a suspended order is unsuspended.',
        'subject' => 'Your order has been unsuspended - {{package_name}} #{{order_id}}',
        'body' => <<<'BODY'
You are receiving this email because your order has been unsuspended.
Your service is now active again.
**Order Details:**
BODY,
        'button_text' => 'View Order',
        'placeholders' => [
            'package_name' => 'Package name',
            'order_id' => 'Order ID',
            'cycle' => 'Price and billing cycle',
            'status' => 'Order status',
            'due_date' => 'Due date',
        ],
    ],

    'order.terminated' => [
        'name' => 'Order terminated',
        'group' => 'Orders',
        'description' => 'Sent when an order is terminated.',
        'subject' => 'Your order has been terminated - {{package_name}} #{{order_id}}',
        'body' => <<<'BODY'
You are receiving this email because your order has been terminated.
**Order Details:**
BODY,
        'button_text' => 'View Order',
        'placeholders' => [
            'package_name' => 'Package name',
            'order_id' => 'Order ID',
            'cycle' => 'Price and billing cycle',
            'status' => 'Order status',
            'due_date' => 'Due date',
        ],
    ],

    'order.renewed.balance' => [
        'name' => 'Order renewed with balance',
        'group' => 'Orders',
        'description' => 'Sent when an order is renewed using account balance.',
        'subject' => 'Order Renewed Successfully - {{package_name}} #{{order_id}}',
        'body' => <<<'BODY'
You are receiving this email because your order has been successfully renewed using your account balance.
**Order Details:**
BODY,
        'button_text' => 'View Order',
        'placeholders' => [
            'package_name' => 'Package name',
            'order_id' => 'Order ID',
            'cycle' => 'Price and billing cycle',
            'status' => 'Order status',
            'due_date' => 'Due date',
        ],
    ],

    'order.renewal.insufficient_balance' => [
        'name' => 'Order renewal failed',
        'group' => 'Orders',
        'description' => 'Sent when auto-renewal fails because the account balance is too low.',
        'subject' => 'Order Renewal Failed - {{package_name}} #{{order_id}}',
        'body' => <<<'BODY'
You are receiving this email because your order renewal has failed due to insufficient account balance.
If you wish to disable auto-balance renewal, you can do so on the order management page.

Please add funds to your account to renew your order and avoid service interruption.
**Order Details:**
BODY,
        'button_text' => 'View Order',
        'placeholders' => [
            'package_name' => 'Package name',
            'order_id' => 'Order ID',
            'cycle' => 'Price and billing cycle',
            'status' => 'Order status',
            'due_date' => 'Due date',
        ],
    ],

    'order.extended' => [
        'name' => 'Order extended',
        'group' => 'Orders',
        'description' => 'Sent when an administrator extends an order and includes a message.',
        'subject' => 'Your order has been extended',
        'body' => <<<'BODY'
{{message}}
BODY,
        'button_text' => 'View Order',
        'placeholders' => [
            'message' => 'Message written by the administrator',
            'package_name' => 'Package name',
            'order_id' => 'Order ID',
            'cycle' => 'Price and billing cycle',
            'status' => 'Order status',
            'due_date' => 'Due date',
        ],
    ],

    'order.transferred' => [
        'name' => 'Order transferred',
        'group' => 'Orders',
        'description' => 'Sent to the new owner when an administrator transfers an order.',
        'subject' => 'You now have access to this order',
        'body' => <<<'BODY'
{{message}}
BODY,
        'button_text' => 'View Order',
        'placeholders' => [
            'message' => 'Message written by the administrator',
            'package_name' => 'Package name',
            'order_id' => 'Order ID',
            'cycle' => 'Price and billing cycle',
            'status' => 'Order status',
            'due_date' => 'Due date',
        ],
    ],

    'order.member.invited' => [
        'name' => 'Order member invited',
        'group' => 'Orders',
        'description' => 'Sent when a user is invited to manage an order.',
        'subject' => 'You have been invited to manage {{package_name}}',
        'body' => <<<'BODY'
You have been added as a member to the order for {{package_name}}.
If you don't have an account, you can create one using the email: {{member_email}}.
The invitation will appear in your account once you log in.
BODY,
        'button_text' => 'View Invite',
        'placeholders' => [
            'package_name' => 'Package name',
            'member_email' => 'Invited email address',
        ],
    ],

    'order.member.accepted' => [
        'name' => 'Order member accepted',
        'group' => 'Orders',
        'description' => 'Sent to the order owner when an invite is accepted.',
        'subject' => '{{username}} has accepted your invite to manage {{package_name}}',
        'body' => <<<'BODY'
{{username}} has accepted your invite to manage {{package_name}}.
Members can be viewed in the order details or with the button below.
BODY,
        'button_text' => 'View Members',
        'placeholders' => [
            'package_name' => 'Package name',
            'username' => 'Username of the member who accepted',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payments
    |--------------------------------------------------------------------------
    */

    'payment.paid' => [
        'name' => 'Payment paid',
        'group' => 'Payments',
        'description' => 'Sent when a payment is successfully processed.',
        'subject' => 'Payment was successfully processed',
        'body' => <<<'BODY'
You are receiving this email because your payment was successfully processed.
**Payment Details:**
BODY,
        'button_text' => 'View Invoice',
        'placeholders' => [
            'description' => 'Payment description',
            'amount' => 'Formatted amount',
            'transaction_id' => 'Transaction ID',
            'date' => 'Payment date',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Subscriptions
    |--------------------------------------------------------------------------
    */

    'subscription.activated' => [
        'name' => 'Subscription activated',
        'group' => 'Subscriptions',
        'description' => 'Sent when a subscription becomes active.',
        'subject' => 'Your Subscription is now Active',
        'body' => <<<'BODY'
You are receiving this email because your subscription has been activated.
**Subscription Details:**
BODY,
        'button_text' => 'View Subscription',
        'placeholders' => [
            'description' => 'Subscription description',
            'amount' => 'Formatted amount and frequency',
            'gateway' => 'Gateway name',
            'subscription_id' => 'Gateway subscription ID',
        ],
    ],

    'subscription.cancelled' => [
        'name' => 'Subscription cancelled',
        'group' => 'Subscriptions',
        'description' => 'Sent when a subscription is cancelled.',
        'subject' => 'Your Subscription has been Cancelled',
        'body' => <<<'BODY'
You are receiving this email because your subscription has been cancelled.
{{active_until}}
**Subscription Details:**
BODY,
        'button_text' => 'View Subscription',
        'placeholders' => [
            'description' => 'Subscription description',
            'amount' => 'Formatted amount and frequency',
            'gateway' => 'Gateway name',
            'subscription_id' => 'Gateway subscription ID',
            'active_until' => 'Optional line describing when access ends',
        ],
    ],

    'subscription.inactive' => [
        'name' => 'Subscription inactive',
        'group' => 'Subscriptions',
        'description' => 'Sent when a subscription is no longer active.',
        'subject' => 'Your Subscription is no longer Active',
        'body' => <<<'BODY'
You are receiving this email because your subscription is no longer active.
**Subscription Details:**
BODY,
        'button_text' => 'View Subscription',
        'placeholders' => [
            'description' => 'Subscription description',
            'amount' => 'Formatted amount and frequency',
            'gateway' => 'Gateway name',
            'subscription_id' => 'Gateway subscription ID',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tickets
    |--------------------------------------------------------------------------
    */

    'tickets.created' => [
        'name' => 'Ticket opened',
        'group' => 'Tickets',
        'description' => 'Sent to ticket members when a new ticket is opened.',
        'subject' => 'Ticket opened: {{ticket_title}}',
        'body' => <<<'BODY'
A new support ticket {{ticket_number}} was opened in {{department}}.
{{preview}}
{{guest_note}}
BODY,
        'button_text' => 'View ticket',
        'placeholders' => [
            'ticket_title' => 'Ticket title',
            'ticket_number' => 'Ticket number',
            'department' => 'Department name',
            'preview' => 'Message preview',
            'guest_note' => 'Extra note for guest requesters',
        ],
    ],

    'tickets.replied' => [
        'name' => 'Ticket reply',
        'group' => 'Tickets',
        'description' => 'Sent to subscribed members when someone replies to a ticket.',
        'subject' => 'New reply on ticket {{ticket_number}}',
        'body' => <<<'BODY'
{{author_name}} replied to {{ticket_number}}: {{ticket_title}}
{{preview}}
BODY,
        'button_text' => 'View reply',
        'placeholders' => [
            'ticket_title' => 'Ticket title',
            'ticket_number' => 'Ticket number',
            'author_name' => 'Name of the person who replied',
            'preview' => 'Reply preview',
        ],
    ],

    'tickets.closed' => [
        'name' => 'Ticket closed',
        'group' => 'Tickets',
        'description' => 'Sent to subscribed members when a ticket is closed.',
        'subject' => 'Ticket closed: {{ticket_title}}',
        'body' => <<<'BODY'
{{close_message}}
BODY,
        'button_text' => 'View ticket',
        'placeholders' => [
            'ticket_title' => 'Ticket title',
            'ticket_number' => 'Ticket number',
            'close_message' => 'Closing message, including inactivity notices',
        ],
    ],

    'tickets.member_invited' => [
        'name' => 'Ticket member invited',
        'group' => 'Tickets',
        'description' => 'Sent when someone is added to a ticket.',
        'subject' => 'You were added to ticket {{ticket_number}}',
        'body' => <<<'BODY'
{{inviter_name}} invited you to the ticket "{{ticket_title}}".
Open the ticket to read the conversation and reply.
BODY,
        'button_text' => 'Open ticket',
        'placeholders' => [
            'ticket_title' => 'Ticket title',
            'ticket_number' => 'Ticket number',
            'inviter_name' => 'Name of the person who sent the invite',
        ],
    ],

    'tickets.department.created' => [
        'name' => 'Department: new ticket',
        'group' => 'Tickets',
        'description' => 'Sent to the department notification address when a ticket is opened.',
        'subject' => 'New ticket {{ticket_number}}: {{ticket_title}}',
        'body' => <<<'BODY'
{{requester_name}} opened a ticket in {{department}}.
{{preview}}
BODY,
        'button_text' => 'Open in admin',
        'placeholders' => [
            'ticket_title' => 'Ticket title',
            'ticket_number' => 'Ticket number',
            'department' => 'Department name',
            'requester_name' => 'Name of the person who opened the ticket',
            'preview' => 'Message preview',
        ],
    ],

    'tickets.department.replied' => [
        'name' => 'Department: new reply',
        'group' => 'Tickets',
        'description' => 'Sent to the department notification address when a customer replies.',
        'subject' => 'New reply on {{ticket_number}}: {{ticket_title}}',
        'body' => <<<'BODY'
{{author_name}} replied to the ticket.
{{preview}}
BODY,
        'button_text' => 'Open in admin',
        'placeholders' => [
            'ticket_title' => 'Ticket title',
            'ticket_number' => 'Ticket number',
            'author_name' => 'Name of the person who replied',
            'preview' => 'Reply preview',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Servers
    |--------------------------------------------------------------------------
    */

    'server.pterodactyl.account_created' => [
        'name' => 'Pterodactyl account created',
        'group' => 'Servers',
        'description' => 'Sent when a game panel account is created for an order.',
        'subject' => 'Game Panel Account Created',
        'body' => <<<'BODY'
Your account has been created on the game panel.
You can login using the following details:
Email: {{panel_email}}
Password: {{panel_password}}
BODY,
        'button_text' => 'Login to Game Panel',
        'placeholders' => [
            'panel_email' => 'Panel login email',
            'panel_password' => 'Panel login password',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin
    |--------------------------------------------------------------------------
    */

    'admin.orders.suspended_batch' => [
        'name' => 'Orders suspended by scheduler',
        'group' => 'Admin',
        'description' => 'Sent to the primary admin when the scheduler suspends overdue orders.',
        'subject' => '{{count}} Orders were suspended by the system',
        'body' => <<<'BODY'
**{{count}} Orders** were suspended because they were **{{grace_period}} days** past their due date:
BODY,
        'button_text' => 'View Suspended Orders',
        'placeholders' => [
            'count' => 'Number of orders',
            'grace_period' => 'Grace period in days',
        ],
    ],

    'admin.orders.terminated_batch' => [
        'name' => 'Orders terminated by scheduler',
        'group' => 'Admin',
        'description' => 'Sent to the primary admin when the scheduler terminates overdue orders.',
        'subject' => '{{count}} Orders were terminated by the system',
        'body' => <<<'BODY'
**{{count}} Orders** were terminated because they were **{{grace_period}} days** past their due date:
BODY,
        'button_text' => 'View Terminated Orders',
        'placeholders' => [
            'count' => 'Number of orders',
            'grace_period' => 'Grace period in days',
        ],
    ],

];
