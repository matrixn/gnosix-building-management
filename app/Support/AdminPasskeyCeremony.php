<?php

namespace App\Support;

use Spatie\LaravelPasskeys\Actions\ConfigureCeremonyStepManagerFactoryAction;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;

class AdminPasskeyCeremony extends ConfigureCeremonyStepManagerFactoryAction
{
    public function execute(): CeremonyStepManagerFactory
    {
        $factory = parent::execute();
        $factory->setAllowedOrigins([config('platform.passkey_origin')], false);

        return $factory;
    }
}
