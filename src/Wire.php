<?php

namespace Eelf\Protobuf;

class Wire {
    const TYPE_DOUBLE = 1, TYPE_FLOAT = 2, TYPE_INT64 = 3, TYPE_UINT64 = 4, TYPE_INT32 = 5, TYPE_FIXED64 = 6, TYPE_FIXED32 = 7, TYPE_BOOL = 8, TYPE_STRING = 9, TYPE_GROUP = 10,  // Tag-delimited aggregate.
    TYPE_MESSAGE = 11,  // Length-delimited aggregate.
    TYPE_BYTES = 12, TYPE_UINT32 = 13, TYPE_ENUM = 14, TYPE_SFIXED32 = 15, TYPE_SFIXED64 = 16, TYPE_SINT32 = 17,  // Uses ZigZag encoding.
    TYPE_SINT64 = 18;  // Uses ZigZag encoding.
    const MESSAGE_TYPE_TO_WIRE_TYPE = [
        self::TYPE_INT32 => self::WT_VARINT,
        self::TYPE_INT64 => self::WT_VARINT,
        self::TYPE_UINT32 => self::WT_VARINT,
        self::TYPE_UINT64 => self::WT_VARINT,
        self::TYPE_SINT32 => self::WT_VARINT,
        self::TYPE_SINT64 => self::WT_VARINT,
        self::TYPE_BOOL => self::WT_VARINT,
        self::TYPE_ENUM => self::WT_VARINT,
        self::TYPE_FIXED64 => self::WT_FIXED64,
        self::TYPE_SFIXED64 => self::WT_FIXED64,
        self::TYPE_DOUBLE => self::WT_FIXED64,
        self::TYPE_STRING => self::WT_LENGTH,
        self::TYPE_BYTES => self::WT_LENGTH,
        self::TYPE_MESSAGE => self::WT_LENGTH,
        //packed repeated fields => self::TYPE_LENGTH,
        self::TYPE_FIXED32 => self::WT_FIXED32,
        self::TYPE_SFIXED32 => self::WT_FIXED32,
        self::TYPE_FLOAT => self::WT_FIXED32,
    ];
    const WT_VARINT = 0, WT_FIXED64 = 1, WT_LENGTH = 2, WT_FIXED32 = 5;

    public static function varintEncode($word) {
        $res = '';
        while ($word) {
            $byte = $word & 0x7f;
            $word >>= 7;
            if ($word) $byte |= 0x80;
            $res .= chr($byte);
        }
        return $res;
    }

    public static function encodeField($value, $type, $tag) {
        if ($type == self::TYPE_STRING) {
            if (!is_string($value)) return [null, "not a string (" . var_export($value, 1) . " $type $tag)"];
            $content = self::varintEncode(strlen($value)) . $value;
        } else if ($type == self::TYPE_INT32) {
            $content = self::varintEncode($value);
        } else if ($type == self::TYPE_ENUM) {
            $content = self::varintEncode($value);
        } else {
            if (!$value instanceof Message) return [null, "not a message"];
            [$value, $err] = $value->toBytes();
            if ($err) return [null, "error encoding field: $err"];
            $content = self::varintEncode(strlen($value)) . $value;
        }
        return [self::varintEncode($tag << 3 | (self::MESSAGE_TYPE_TO_WIRE_TYPE[$type] ?? self::WT_LENGTH)) . $content, null];
    }

    public static function varintDecode($bytes, &$pos) {
        $word = $shift = 0;
        do {
            if (!isset($bytes[$pos])) return false;
            $byte = ord($bytes[$pos]);
            $word |= ($byte & 0x7f) << $shift;
            $shift += 7;
            $pos++;
        } while ((bool)($byte & 0x80));
        return $word;
    }

    public static function decodeFields($data) {
        $pos = 0;
        while (isset($data[$pos])) {
            $v = self::varintDecode($data, $pos);
            if ($v === false) return "bad key at pos:$pos";
            $tag = $v >> 3;
            $wt = $v & 0x07;
            if ($wt == self::WT_LENGTH) {
                $length = self::varintDecode($data, $pos);
                if ($length === false) return "wt:2 bad length at pos:$pos";

                $inner = substr($data, $pos, $length);
                if (($actual_length = strlen($inner)) !== $length) return "inner length $actual_length is not as expected $length";
                $pos += $length;

                yield [$tag, $wt, $inner];
            } else if ($wt == self::WT_VARINT) {
                $value = self::varintDecode($data, $pos);
                if ($value === false) return "wt:0 bad value at pos:$pos";
                yield [$tag, $wt, $value];
            } else {
                return "unknown wt:$wt";
            }
        }
    }
}
