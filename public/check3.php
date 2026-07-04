<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

if (is_plugin_active('captcha')) {
    \Botble\Captcha\Facades\Captcha::shouldReceive('isEnabled')->andReturn(false);
}

$controller = app(\Botble\RealEstate\Http\Controllers\Fronts\PublicController::class);
$mockRequest = \Botble\RealEstate\Http\Requests\SendConsultRequest::create(
    '/send-consult',
    'POST',
    [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'phone' => '1234567890',
        'content' => 'I would like more information',
        'type' => 'project',
        'data_id' => 1
    ]
);

try {
    $res = $controller->postSendConsult($mockRequest);
    echo "Response Class: " . get_class($res) . "<br>";
    echo "Response Content: " . $res->getContent() . "<br>";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "<br>" . nl2br($e->getTraceAsString());
}
