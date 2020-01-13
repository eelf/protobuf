<?php

namespace Eelf\Protobuf;

abstract class Message {
    public $fields = [];
    protected static $ds = [];
    protected static $tag_by_name = [];

    public static function desc() {
        return static::$ds;
    }

    public function toBytes() {
        $res = '';
        foreach ($this->fields as $name => $value) {
            if (($tag = static::$tag_by_name[$name] ?? null) === null) return [null, "no field found by name $name"];
            if (($field_ds = static::$ds[$tag] ?? null) === null) return [null, "no descriptor for field $name($tag)"];

            if ($field_ds['repeated'] ?? false) {
                if (!is_array($value)) return [null, "message field $name is not an array"];
                foreach ($value as $val_idx => $val) {
                    [$bytes, $err] = Wire::encodeField($val, $field_ds['type'], $tag);
                    if ($err) return [null, "error encoding repeated element $val_idx for field $name:\n$err"];
                    $res .= $bytes;
                }
            } else {
                [$bytes, $err] = Wire::encodeField($value, $field_ds['type'], $tag);
                if ($err) return [null, "error encoding field $name:\n$err"];
                $res .= $bytes;
            }
        }
        return [$res, null];
    }

    public static function fromBytes($data) {
        $message = new static();
        foreach ($g = Wire::decodeFields($data) as [$tag, $wt, $value]) {
            $type = static::$ds[$tag]['type'] ?? null;
            $name = static::$ds[$tag]['name'] ?? $tag;
            $repeated = static::$ds[$tag]['repeated'] ?? false;

            if ($wt == Wire::WT_LENGTH) {
                if ($type == Wire::TYPE_STRING || $type === null) {
                    $inner_decoded = $value;
                } else if ($type == Wire::TYPE_INT32) {
                    $inner_decoded = [];
                    for ($cur_pos = 0; $cur_pos < strlen($value);) {
                        $inner_decoded[] = Wire::varintDecode($value, $cur_pos);
                    }
                } else {
                    if (!class_exists($type)) return [null, "no class $type for decoding tag:$tag wt:$wt"];
                    [$inner_decoded, $err] = $type::fromBytes($value);
                    if ($err) return [null, "error decoding tag:$tag wt:$wt $type:\n$err"];
                }

                if ($repeated) $message->fields[$name][] = $inner_decoded;
                else $message->fields[$name] = $inner_decoded;
            } else if ($wt == Wire::WT_VARINT) {
                if ($repeated) $message->fields[$name][] = $value;
                else $message->fields[$name] = $value;
            } else {
                return [null, "unknown wt:$wt"];
            }
        }
        if ($err = $g->getReturn()) {
            return [null, $err];
        }
        return [$message, null];
    }
}
