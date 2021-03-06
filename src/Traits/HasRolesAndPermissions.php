<?php

namespace Sow\Roles\Traits;

use Closure;
use Phalcon\Mvc\Model\ResultInterface;
use Phalcon\Mvc\Model\Resultset;
use Phalcon\Mvc\Model\ResultsetInterface;
use Sow\Roles\Models\Permissions;
use Sow\Roles\Models\Roles;
use Sow\Roles\Models\RolesPermissions;
use Sow\Roles\Models\RolesUsers;

trait HasRolesAndPermissions
{
    /**
     * Always use getter/setter, event inside the class.
     * See coresponding methods for further informations.
     *
     * @var Resultset
     */
    protected $roles;

    /**
     * Always use getter/setter, event inside the class.
     * See coresponding methods for further informations.
     *
     * @var Resultset
     */
    protected $permissions;

    /**
     * Setup a 1-n relation between two models.
     *
     * @param $fields
     * @param $referenceModel
     * @param $referencedFields
     * @param null $options
     * @return \Phalcon\Mvc\Model\Relation
     */
    protected abstract function hasMany(
        $fields,
        $referenceModel,
        $referencedFields,
        $options = null
    );

    /**
     * Setup an n-n relation between two models, through an intermediate relation.
     *
     * @param $fields
     * @param $intermediateModel
     * @param $intermediateFields
     * @param $intermediateReferencedFields
     * @param $referenceModel
     * @param $referencedFields
     * @param null $options
     * @return \Phalcon\Mvc\Model\Relation
     */
    protected abstract function hasManyToMany(
        $fields,
        $intermediateModel,
        $intermediateFields,
        $intermediateReferencedFields,
        $referenceModel,
        $referencedFields,
        $options = null
    );

    /**
     * Returns the models manager related to the entity instance.
     *
     * @return \Phalcon\Mvc\Model\ManagerInterface
     */
    public abstract function getModelsManager();

    /**
     * Returns related records based on defined relations.
     *
     * @param string $alias
     * @param array $arguments
     * @return \Phalcon\Mvc\Model\ResultsetInterface
     */
    public abstract function getRelated($alias, $arguments = null);

    /**
     * Initialize relations.
     *
     * @return void
     */
    public function hasRolesAndPermissions()
    {
        $this->hasMany(
            "id",
            RolesUsers::class,
            "user_id",
            ['alias' => 'rolesPivot']
        );

        $this->hasManyToMany(
            "id",
            RolesUsers::class,
            "user_id",
            "role_id",
            Roles::class,
            "id",
            ['alias' => 'roles']
        );
    }

    /**
     * Return the related roles.
     *
     * @param array $parameters
     * @return Roles[]
     */
    public function getRoles($parameters = null)
    {
        if ($this->roles === null) {
            $this->roles = $this->toHydratedArrary(
                $this->getRelated("roles", $parameters), Roles::class
            );
        }

        return $this->roles;
    }

    /**
     * Check if the user has a role.
     *
     * @param string $role
     * @return bool
     */
    public function is($role)
    {
        return $this->hasRole(new Roles(["slug" => $role]));
    }

    /**
     * Check if the user has role.
     *
     * @param Roles $role
     * @return bool
     */
    public function hasRole(Roles $role)
    {
        $byName = function (Roles $record) use ($role) {
            return $record->slug === $role->slug ? $record : false;
        };

        return (bool)array_filter($this->getRoles(), $byName);
    }

    /**
     * Attach role to a user.
     *
     * @param Roles $role
     * @return bool
     */
    public function attachRole(Roles $role)
    {
        if ($this->hasRole($role)) {
            return true;
        }

        $this->setRoles([$role]);

        return $this->save();
    }

    /**
     * Attach roles to a user.
     *
     * @param Roles[]|ResultInterface $roles
     * @return bool
     */
    public function attachAllRoles($roles)
    {
        $rolesToAttach = [];

        foreach ($roles as $role) {
            if(!$this->hasRole($role)) {
                $rolesToAttach[] = $role;
            }
        }

        $this->setRoles($rolesToAttach);

        return $this->save();
    }

    /**
     * Detach role from a user.
     *
     * @param Roles $role
     * @return int
     */
    public function detachRole(Roles $role)
    {
        $byName = function (RolesUsers $record) use ($role) {
            return $record->role_id === $role->id;
        };

        return $this->_detachRoles($byName);
    }

    /**
     * Detach all permissions from a user.
     *
     * @return int
     */
    public function detachAllRoles()
    {
        return $this->_detachRoles();
    }

    /**
     * Detach roles based on filter and clear cache.
     *
     * @param Closure|null $filter
     * @return bool
     */
    protected function _detachRoles(Closure $filter = null) {
        if(!$this->rolesPivot->delete($filter)) {
            return false;
        }

        $this->roles = null;

        return true;
    }

    /**
     * Return the related permissions.
     *
     * @return Permissions[]
     */
    public function getPermissions()
    {
        if ($this->permissions === null) {
            $roles = $this->getRoles();
            $roleIDs = array_column($roles, 'id');

            $builder = $this->getModelsManager()->createBuilder();
            $builder->columns('DISTINCT p.id, p.name, p.slug, p.description');
            $builder->from(['rp' => RolesPermissions::class]);
            $builder->join(Permissions::class, 'rp.permission_id = p.id', 'p');
            $builder->inWhere('rp.role_id', $roleIDs);

            $resultSet = $builder->getQuery()->execute();

            $this->permissions = $this->toHydratedArrary($resultSet, Permissions::class);
        }

        return $this->permissions;
    }

    /**
     * Check if the user has a permission.
     *
     * @param string $permission
     * @return bool
     */
    public function can($permission)
    {
        return $this->hasPermission(new Permissions(["slug" => $permission]));
    }


    /**
     * Check if user is allowed to perform an action that requires permission,
     *
     * @param $permission
     * @return bool
     */
    public function isAllowed($permission)
    {
        return $this->can($permission);
    }

    /**
     * Check if the user has a permission.
     *
     * @param Permissions $permission
     * @return bool
     */
    public function hasPermission(Permissions $permission)
    {
        $byName = function (Permissions $record) use ($permission) {
            return $record->slug === $permission->slug ? $record : false;
        };

        return (bool)array_filter($this->getPermissions(), $byName);
    }

    /**
     * Put roles into _related property and clear roles cache.
     *
     * @param array $roles
     * @return void
     */
    private function setRoles(array $roles)
    {
        // getRoles caches the result set inside roles property.
        // Magic __set doesn't set this property, so to avoid inconsistent
        // state between model and database after persisting new roles,
        // we need to set roles to null, to force refetching on next access.
        $this->roles = null;

        // We need to explicitly call magic __set to prepare object
        // for persisting relations on save by seting _relation property.
        $this->__set("roles", $roles);
    }

    /**
     * Returns result set as array of $modelClass instances.
     *
     * @param ResultsetInterface $resultset
     * @param $modelClass
     * @return array
     */
    private function toHydratedArrary(ResultsetInterface $resultset, $modelClass)
    {
        return array_map(
            function($row) use ($modelClass) {
                return new $modelClass($row);
            },
            $resultset->toArray()
        );
    }
}