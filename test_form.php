<?php

require_once __DIR__ . '/vendor/autoload.php';

use Enoc\Login\Http\Forms\CreateUserForm;

echo "Test: CreateUserForm con rol facturador\n\n";

$_POST = [
    'name' => 'Test User',
    'email' => 'test@test.com',
    'password' => 'password123',
    'confirm_password' => 'password123',
    'role' => 'facturador',
    'csrf_token' => 'test'
];

$form = new CreateUserForm($_POST);
$result = $form->handle();

if (isset($result['errors'])) {
    echo "❌ Errores encontrados:\n";
    print_r($result['errors']);
} else {
    echo "✅ Form válido!\n";
    echo "DTO creado:\n";
    var_dump($result['dto']);
}