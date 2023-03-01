<?php

namespace App\Http\Controllers\Stimular\Azadi\Stimular;



use App\Models\Identity\User;
use Azadi\Stimular\StimularController as Azadi;
use Illuminate\Http\Request;

class StimularController extends Azadi
{

    public function viewer(Request $request)
    {
        // TODO: You can check permission
        $token = $request->token ?? 'null';
        $token_obj = $this->getTokenObj($token);
        $user = User::where('id', $token_obj['user'])->first();

        return parent::viewer($request);
    }

    public function designer(Request $request)
    {
        // TODO: You can check permission
        $args = parent::designer($request);
        $token = $request->token ?? 'null';
        $token_obj = $this->getTokenObj($token);
        $user = User::where('id', $token_obj['user'])->first();
        if (!$user->is_admin) {
            $args->allowed = false;
            $args->error_msg = 'Access Forbidden';
        }

        return $args;
    }

    public function saveReport($token, $args)
    {
        $args = parent::saveReport($token, $args);

        // TODO: You can check permission
        $token_obj = $this->getTokenObj($token);
        $user = User::where('id', $token_obj['user'])->first();

        if (!$user->is_admin) {
            $args->allowed = false;
            $args->error_msg = 'Access Forbidden';
        }

        return $args;
    }

    public function globalVariables($token, $args)
    {
        // TODO: You can change the values of the variables used in the report.
        // The new values will be passed to the report generator.
        /*
        $args->variables['VariableString']->value = 'Value from Server-Side';
        $args->variables['VariableDateTime']->value = '2020-01-31 22:00:00';

        $args->variables['VariableStringRange']->value->from = 'Aaa';
        $args->variables['VariableStringRange']->value->to = 'Zzz';

        $args->variables['VariableStringList']->value[0] = 'Test';
        $args->variables['VariableStringList']->value = ['1', '2', '2'];
        */

        $args->variables['date_fa'] = $args->variables['date_fa'] ?? new \stdClass();
        $args->variables['date_fa']->value = '1401/03/18';

        return parent::globalVariables($token, $args);
    }

}
