<?php
/**
 * @var string $ns
 * @var string $class
 * @var string $tags
 * @var string $tag_by_name
 * @var array $methods
 */ ?>
<?= $ns ?>

class <?= $class ?> extends \Eelf\Protobuf\Message {
    protected static $ds = <?= $tags ?>;
    protected static $tag_by_name = <?= $tag_by_name ?>;

<?php foreach ($methods as ['php_name' => $php_name, 'name' => $name]):?>
    public function has<?= $php_name ?>() {
        return array_key_exists('<?= $name ?>', $this->fields);
    }

    public function get<?= $php_name ?>() {
        return $this->fields['<?= $name ?>'];
    }

    public function set<?= $php_name ?>($value) {
        $this->fields['<?= $name ?>'] = $value;
    }

    public function append<?= $php_name ?>($value) {
        $this->fields['<?= $name ?>'][] = $value;
    }
<?php endforeach ?>
}
