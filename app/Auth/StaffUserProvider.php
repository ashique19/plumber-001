<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;

/**
 * Tenant staff auth must not use {@see \App\Tenancy\Scopes\TenantScope}: session
 * restore can run before route binding sets tenant context.
 */
class StaffUserProvider extends EloquentUserProvider
{
    public function retrieveById($identifier)
    {
        $model = $this->createModel();

        return $this->newScopedQuery($model)
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();
    }

    public function retrieveByToken($identifier, $token)
    {
        $model = $this->createModel();

        return $this->newScopedQuery($model)
            ->where($model->getAuthIdentifierName(), $identifier)
            ->where($model->getRememberTokenName(), $token)
            ->first();
    }

    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials) ||
           (count($credentials) === 1 &&
            array_key_exists('password', $credentials))) {
            return;
        }

        $query = $this->newScopedQuery($this->createModel());

        foreach ($credentials as $key => $value) {
            if (! str_contains($key, 'password')) {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    protected function newScopedQuery($model)
    {
        return $model->newQueryWithoutScopes();
    }
}
