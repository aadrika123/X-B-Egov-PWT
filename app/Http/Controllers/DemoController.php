<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class DemoController extends Controller
{

    public function waterConnection(Request $req)
    {
        $data = User::select('application_no', 'ward_id')
            ->join('water_applications', 'water_applications.user_id', 'users.id')
            ->where('users.mobile', $req->mobileNo)
            ->get();
        return $data;
    }

    /**
     * | This is for whatsaap testing purpose
     */
    public function testWhatsaap(Request $req)
    {
        # Send Message behalf of registration
        $whatsaapResponse = (Whatsapp_Send(
            "8797770238",
            "hello_world",                     // Set at env or database and 
            [
                "conten_type" => "text",
                [
                    "Mrinal",                    // Static
                ]
            ]
        ));
        return $whatsaapResponse;
    }
}
