<?php

return [
    'validation' => [
        'email_required' => 'Please enter your email address.',
        'email_invalid' => 'Please enter a valid email address.',
        'email_max' => 'Email address is too long.',
        'code_required' => 'Please enter your verification code.',
        'code_size' => 'Verification code must be 6 digits.',
        'code_numeric' => 'Verification code must contain only numbers.',
    ],
    'verify' => [
        'invalid_code' => 'Invalid verification code.',
        'success' => 'Successfully logged in.',
        'rate_limited' => 'Too many attempts. Please try again in :minutes minutes.',
    ],
    'challenge' => [
        'title' => 'Your Login Code',
        'preview' => 'Here is your 6-digit login code for Agent AI',
        'greeting' => 'Welcome back! Please use the following code to log in to your account.',
        'code_instruction' => 'Your 6-digit login code:',
        'expiry_notice' => 'This code will expire in :minutes minutes.',
        'security_notice' => 'If you did not request this code, please ignore this email.',
    ],
    'magic_link' => [
        'title' => 'Login Link',
        'preview' => 'Click to log in to Agent AI',
        'greeting' => 'Welcome back! Click the button below to log in to your account.',
        'button' => 'Log In',
        'expiry_notice' => 'This link will expire in :minutes minutes.',
        'security_notice' => 'If you did not request this link, please ignore this email.',
    ],
];
