#!/usr/bin/env php
<?php
/**
 * PHP plugin for protoc

# generate full messages using bootstrapped ones
protoc --kek_out=server/vendor/eelf/protobuf/proto/ --kek_opt='\Eelf\Protobuf\Renderer' --plugin=protoc-gen-kek=server/vendor/eelf/protobuf/plugin.php -I$HOME/homebrew/include/google/protobuf/compiler $HOME/homebrew/include/google/protobuf/compiler/plugin.proto

# generate messages for project
protoc --kek_out=proto --kek_opt='\Eelf\WebJson\Renderer,\Eelf\WebJson\RendererJs' --plugin=protoc-gen-kek=server/vendor/eelf/protobuf/plugin.php proto/s.proto

 */

set_error_handler(
    function ($errno, $errstr) {
        $trace_str = implode(
            "\n",
            array_map(
                function ($frame) {
                    static $idx;
                    return sprintf('#%d %s(%d): %s()', $idx++, $frame['file'] ?? '-', $frame['line'] ?? '-', $frame['function'] ?? '-');
                },
                (new \Exception)->getTrace()
            )
        );
        fwrite(STDERR, "error $errno:$errstr\n$trace_str\n");
        exit(1);
    }
);

register_shutdown_function(
    function () {
        if ($err = error_get_last()) {
            ['type' => $type, 'message' => $message, 'file' => $file, 'line' => $line] = $err;
            $types = [E_ERROR => 'E_ERROR'];
            $type = $types[$type] ?? $type;
            fwrite(STDERR, "$type $file:$line\n$message\n");
        }
    }
);

foreach ([
    __DIR__ . '/vendor/autoload.php',//this project installs its own dependencies
    __DIR__ . '/../../autoload.php',//this projects is installed as a dependency
     ] as $path) {
    if (is_file($path)) {
        require_once $path;
        break;
    }
}

$proto_dir = __DIR__ . '/proto';

if (!is_dir($proto_dir)) mkdir($proto_dir);

$messages = [
    'google.protobuf.compiler.CodeGeneratorRequest' => [
        15 => ['repeated' => true, 'type' => 'google.protobuf.FileDescriptorProto', 'name' => 'proto_file'],
        2 => ['type' => 9, 'name' => 'parameter'],
    ],
    'google.protobuf.FileDescriptorProto' => [
        2 => ['type' => 9, 'name' => 'package'],
        4 => ['repeated' => true, 'type' => 'google.protobuf.DescriptorProto', 'name' => 'message_type'],
    ],
    'google.protobuf.DescriptorProto' => [
        1 => ['type' => 9, 'name' => 'name'],
        2 => ['repeated' => true, 'type' => 'google.protobuf.FieldDescriptorProto', 'name' => 'field'],
        3 => ['repeated' => true, 'type' => 'google.protobuf.DescriptorProto', 'name' => 'nested_type'],
    ],
    'google.protobuf.FieldDescriptorProto' => [
        1 => ['type' => 9, 'name' => 'name'],
        3 => ['type' => 5, 'name' => 'number'],
        4 => ['type' => 14, 'name' => 'label'],
        5 => ['type' => 14, 'name' => 'type'],
        6 => ['type' => 9, 'name' => 'type_name'],
    ],
    'google.protobuf.compiler.CodeGeneratorResponse' => [
        15 => ['repeated' => true, 'type' => 'google.protobuf.compiler.CodeGeneratorResponse.File', 'name' => 'file'],
    ],
    'google.protobuf.compiler.CodeGeneratorResponse.File' => [
        1 => ['type' => 9, 'name' => 'name'],
        15 => ['type' => 9, 'name' => 'content'],
    ],
];

\Eelf\Protobuf\Util::psr4('google\\', __DIR__ . '/proto/google');

foreach ($messages as $full_class_name => $tag_dss) {
    $php_class = \Eelf\Protobuf\Util::protoToPhpclass($full_class_name);
    if (class_exists($php_class)) continue;
    $code = \Eelf\Protobuf\Renderer::generateClass($full_class_name, $tag_dss);
    eval($code);
}

$str = stream_get_contents(STDIN);

[$request, $err] = \google\protobuf\compiler\CodeGeneratorRequest::fromBytes($str);
if ($err !== null) {
    fwrite(STDERR, "decode err: $err\n");
    exit(1);
}

/** @var \google\protobuf\compiler\CodeGeneratorRequest $request */
if (!$request->hasParameter()) {
    fwrite(STDERR, "no renderers specified in opt\n");
    exit(0);
}

$resp = new \google\protobuf\compiler\CodeGeneratorResponse();
foreach (explode(',', $request->getParameter()) as $class) (new $class)($request, $resp);

[$bytes, $err] = $resp->toBytes();
if ($err !== null) {
    fwrite(STDERR, "encode err: $err\n");
    exit(1);
}
echo $bytes;
