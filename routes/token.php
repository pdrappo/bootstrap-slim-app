<?php

/*
 * This file is part of the Slim API skeleton package
 *
 * Copyright (c) 2016 Mika Tuupola
 *
 * Licensed under the MIT license:
 *   http://www.opensource.org/licenses/mit-license.php
 *
 * Project home:
 *   https://github.com/tuupola/slim-api-skeleton
 *
 */

use Ramsey\Uuid\Uuid;
use Firebase\JWT\JWT;
use Tuupola\Base62;

use League\Fractal\Manager;
use League\Fractal\Resource\Item;
use League\Fractal\Resource\Collection;
use League\Fractal\Serializer\ArraySerializer;
use League\Fractal\Serializer\DataArraySerializer;

use Exception\NotFoundException;
use Exception\ForbiddenException;

use App\Models\User;
use App\Transformers\UserTransformer;
use App\Transformers\PermTransformer;

$app->get("/dump", function ($request, $response, $arguments) {
  print_r($this->token);
});

$app->post("/token", function ($request, $response, $arguments) {

    //Obtengo las credenciales del cuerpo del mensaje
    $credentials = $request->getParsedBody();

    // Selecciono el usuario
    $userMapper = $this->spot->mapper("App\Models\User")->where([
        'username' => $credentials['username'],
        'password' => md5($credentials['password'])
        ])->with('perms')->first();

    if(!$userMapper){
      $data = array("status" => "error", "msg" => "");
      if(
      $this->spot->mapper("App\Models\User")
                 ->where(['username' => $credentials['username']])
                ->first()){
                  $msg = "La contraseña es incorrecta";
                  $this->logger->info($msg, $credentials);
                  $data["msg"] = $msg;
                }else{
                  $msg = "El usuario ingresado no existe.";
                  $this->logger->info($msg, $credentials);
                  $data["msg"] = $msg;
                }

      return $response->withStatus(403)
          ->withHeader("Content-Type", "application/json")
          ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

      //throw new NotFoundException("User not found.", 404);
    }


    /* Serialize the response data. */
    $fractal = new Manager();
    $fractal->setSerializer(new ArraySerializer);
    //Para una fila simple utilizar:
        //$resource = new Item($userMapper, new UserTransformer);
    //Para varias filas utilizar:
        //$resource = new Collection($userMapper, new UserTransformer);
    $resource = new Item($userMapper, new UserTransformer);
    $user = $fractal->createData($resource)->toArray();

    $valid_scopes = array();
    foreach ($user['perms']['data'] as $userPerm) {
        $valid_scopes[] = $userPerm['key'];
    }

    $now = new DateTime();
    $future = new DateTime("now +2 hours");
    //$future = new DateTime("now +1 minutes");
    $server = $request->getServerParams();

    $jti = Base62::encode(random_bytes(16));

    $payload = [
        "iat" => $now->getTimeStamp(),
        "exp" => $future->getTimeStamp(),
        "jti" => $jti,
        "user" => array("id" => $user["id"], "username" => $user["username"], "email" => $user["email"]),
        "scope" => $valid_scopes
    ];

    $secret = getenv("JWT_SECRET");
    $token = JWT::encode($payload, $secret, "HS256");
    $data["status"] = "ok";
    $data["token"] = $token;

    return $response->withStatus(201)
        ->withHeader("Content-Type", "application/json")
        ->write(json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
});
