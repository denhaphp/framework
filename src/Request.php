<?php
//------------------------
//· Http-Request操作类
//-------------------------

declare (strict_types = 1);

namespace denha;

use Reflection;
use ReflectionClass;

class Request
{
    // 是否忽略null
    private $isNonNull = false;

    public function arrayToObject(array $params)
    {
        foreach($params as $prop => $val){
            if(property_exists($this,$prop)){
                $this->$prop = $val;
            }
        }

        return $this;
    }

    public function jsonStrToObject(string $jsonStr)
    {
        try {
            $params = json_decode($jsonStr,true);
        } catch (\Throwable $th) {
            throw new Exception("Resolution JSON failed");
        }

        if(is_array($params)){
            foreach($params as $prop => $val){
                if(property_exists($this,$prop)){
                    $this->$prop = $val;
                }
            }
        }

        return $this;

    }

    public function setIsNonNull(bool $bool)
    {
        $this->isNonNull = $bool;

        return $this;
    }

    public function getDatasByIsNonNull(array $datas)
    {
        if($this->isNonNull === false){
            return $datas;
        }

        foreach($datas as $prop => $val){
            if($val === null){
                unset($datas[$prop]);
            }
        }

        return $datas; 
    }

    public function toArrayMapAndKeyToCamel()
    {
        $props = (new ReflectionClass($this))->getProperties();

        $data = [];
        foreach($props as $item){
            $data[strtolower(trim(preg_replace('/[A-Z]/', '_\\0', $item->name), '_'))] = $this->{$item->name};
        }

        return $this->getDatasByIsNonNull($data);
    }

    public function toArrayMap()
    {
        $props = (new ReflectionClass($this))->getProperties();

        $data = [];
        foreach($props as $prop => $val){
            $data[$prop] = $val;
        }

        return $this->getDatasByIsNonNull($data);
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }

    public function __get($name)
    {
        return $this->$name;
    }
}
