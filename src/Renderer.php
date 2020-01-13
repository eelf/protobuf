<?php

namespace Eelf\Protobuf;

class Renderer {
    private function getFields(array $fields) {
        foreach ($fields as $f) {
            /** @var $f \google\protobuf\FieldDescriptorProto */
            yield $f->getNumber() => [
                'repeated' => $f->getLabel() == 3 ? true : false,
                'type' => $f->getType() == 11 ? trim($f->getTypeName(), '.') : (int)$f->getType(),
                'name' => $f->getName(),
            ];
        }
    }

    private function getMessages(string $package, array $messages) {
        foreach ($messages as $m) {
            /** @var $m \google\protobuf\DescriptorProto */
            yield [
                'package' => $package,
                'name' => $m->getName(),
                'fields' => $m->hasField() ? iterator_to_array($this->getFields($m->getField())) : [],
            ];
            if ($m->hasNestedType()) {
                yield from $this->getMessages($package . '.' . $m->getName(), $m->getNestedType());
            }
        }
    }

    public static function generateClass($full_class_name, $tag_dss) {
        $tag_by_name = $methods = [];
        foreach ($tag_dss as $tag => &$tag_ds) {
            $tag_by_name[$tag_ds['name']] = $tag;

            $type = explode('.', $tag_ds['type']);
            $tag_ds['type'] = count($type) == 1 ? (int)$type[0] : implode('\\', $type);
            $methods[] = [
                'name' => $tag_ds['name'],
                'php_name' => Util::protoToPhpName($tag_ds['name']),
            ];
        }
        $full_class_name = explode('.', $full_class_name);

        $class_name = array_pop($full_class_name);
        $namespace = implode('\\', $full_class_name);

        $code = Util::render(
            __DIR__ . '/php_message.php',
            [
                'ns' => $namespace ? "namespace $namespace;" : '',
                'class' => $class_name,
                'tags' => var_export($tag_dss, true),
                'tag_by_name' => var_export($tag_by_name, true),
                'methods' => $methods,
            ]
        );
        return $code;
    }

    public function __invoke(\google\protobuf\compiler\CodeGeneratorRequest $cgr, \google\protobuf\compiler\CodeGeneratorResponse $resp) {
        $its = array_map(
            function ($proto_file) {
                /** @var $proto_file \google\protobuf\FileDescriptorProto */
                return $this->getMessages($proto_file->hasPackage() ? $proto_file->getPackage() : '', $proto_file->getMessageType());
            },
            $cgr->getProtoFile()
        );
        foreach (Util::iterateGenerators($its) as ['package' => $package, 'name' => $name, 'fields' => $fields]) {
            $file = new \google\protobuf\compiler\CodeGeneratorResponse\File;

            $name = Util::reservedPhp($name);
            $file_name = Util::protoToPath($package) . '/' . $name . '.php';
            $content = "<?php\n" . self::generateClass("$package.$name", $fields);
            $file->setName($file_name);
            $file->setContent($content);

            $resp->appendFile($file);
        }
    }
}
