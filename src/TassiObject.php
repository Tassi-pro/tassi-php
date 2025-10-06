<?php

namespace Tassi;

class TassiObject
{
    protected $id;

    public function __construct($id = null)
    {
        if ($id !== null) {
            $this->id = $id;
        }
    }

    public function refreshFrom(array $values, array $options): void
    {
        foreach ($values as $key => $value) {
            $this->{$key} = $value;
        }
    }

    public function serializeParameters(): array
    {
        $params = [];

        foreach (get_object_vars($this) as $key => $value) {
            if ($key !== 'id' && !is_callable($value)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    public function __get($name)
    {
        return $this->{$name} ?? null;
    }

    public function __set($name, $value)
    {
        $this->{$name} = $value;
    }

    public function __isset($name)
    {
        return isset($this->{$name});
    }

    public function __toString(): string
    {
        $idStr = isset($this->id) ? " id={$this->id}" : "";
        return "<" . get_class($this) . $idStr . ">";
    }
}