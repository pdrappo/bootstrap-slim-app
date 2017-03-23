<?php

use App\Token;

use Slim\Middleware\JwtAuthentication;
use Slim\Middleware\HttpBasicAuthentication;
use Gofabian\Negotiation\NegotiationMiddleware;

$container = $app->getContainer();

$container["token"] = function ($container) {
    return new Token;
};

$container["JwtAuthentication"] = function ($container) {
    return new JwtAuthentication([
        "path" => "/",
        "passthrough" => ["/token"],
        "secure" => false,
        "secret" => getenv("JWT_SECRET"),
        "logger" => $container["logger"],
        "relaxed" => ["localhost"],
        "error" => function ($request, $response, $arguments) {
            $data["status"] = "error";
            $data["message"] = $arguments["message"];
            return $response
                ->withHeader("Content-Type", "application/json")
                ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        },
        "callback" => function ($request, $response, $arguments) use ($container) {
            $container["token"]->hydrate($arguments["decoded"]);
        }
    ]);
};

$container["Negotiation"] = function ($container) {
    return new NegotiationMiddleware([
        "accept" => ["application/json"]
    ]);
};

$app->add("JwtAuthentication");
$app->add("Negotiation");

$container["cache"] = function ($container) {
    return new CacheUtil;
};
