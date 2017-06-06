<?php

namespace Sow\Roles\Models;

use Phalcon\Mvc\Model;

class Permissions extends Model
{
    public $id;

    public $name;

    public $slug;

    public $description;
}