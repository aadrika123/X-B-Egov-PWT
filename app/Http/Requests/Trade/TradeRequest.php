<?php

namespace App\Http\Requests\Trade;

use App\Models\Trade\ActiveTradeLicence;
use App\Repository\Common\CommonFunction;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Config;

class TradeRequest extends FormRequest
{
    protected $_DB_CON_NAME;
    protected $_COMMON_FUNCTION;
    protected $_WF_MASTER_Id;
    protected $_WF_NOTICE_MASTER_Id;
    protected $_MODULE_ID;
    protected $_REF_TABLE;
    protected $_TRADE_CONSTAINT;


    protected $_CURRENT_DATE;
    protected $_CURRENT_DATE_TIME;
    protected $_REX_DATE_YYYY_MM_DD;
    protected $_REX_DATE_YYYY_MM;
    protected $_REX_ALPHA;
    protected $_REX_ALPHA_NUM;
    protected $_REX_ALPHA_NUM_SPACE;
    protected $_REX_ALPHA_SPACE;
    protected $_REX_ALPHA_NUM_DOT_SPACE;
    protected $_REX_ALPHA_NUM_OPS_DOT_MIN_COM_AND_SPACE_SL;
    protected $_REX_NUM_DOT;
    protected $_REX_APPLICATION_TYPE;
    protected $_REX_OWNER_NAME;
    protected $_REX_MOBILE_NO;
    protected $_APPLYCATION_TYPE ;
    protected $_ENG_MARATHI;

    public function __construct()
    {
        $this->_DB_CON_NAME = (new ActiveTradeLicence())->getConnection();
        $this->_COMMON_FUNCTION = new CommonFunction();

        $this->_WF_MASTER_Id = Config::get('workflow-constants.TRADE_MASTER_ID');
        $this->_WF_NOTICE_MASTER_Id = Config::get('workflow-constants.TRADE_NOTICE_ID');
        $this->_MODULE_ID = Config::get('module-constants.TRADE_MODULE_ID');
        $this->_TRADE_CONSTAINT = Config::get("TradeConstant");
        $this->_REF_TABLE = $this->_TRADE_CONSTAINT["TRADE_REF_TABLE"];
        
        $this->_CURRENT_DATE                                = Carbon::now()->format('Y-m-d');
        $this->_CURRENT_DATE_TIME                           = Carbon::now()->format('Y-m-d H:i:s');
        $this->_REX_DATE_YYYY_MM_DD                         = "/^([12]\d{3}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01]))+$/i";
        $this->_REX_DATE_YYYY_MM                            = "/^([12]\d{3}-(0[1-9]|1[0-2]))+$/i";
        $this->_REX_ALPHA                                   = "/^[a-zA-Z]+$/i";
        $this->_REX_ALPHA_SPACE                             = "/^[a-zA-Z\s]+$/i";
        $this->_REX_ALPHA_NUM                               = "/^[a-zA-Z0-9-]+$/i";
        $this->_REX_ALPHA_NUM_SPACE                         = "/^[a-zA-Z0-9- ]+$/i";
        $this->_REX_ALPHA_NUM_DOT_SPACE                     = "/^[a-zA-Z0-9][a-zA-Z0-9\. \s]+$/i";
        $this->_REX_ALPHA_NUM_OPS_DOT_MIN_COM_AND_SPACE_SL  = "/^[a-zA-Z0-9][a-zA-Z0-9\'\.\-\,\&\s\/]+$/i";
        $this->_REX_NUM_DOT                                 = "/^\d+(?:\.\d+)+$/i";
        $this->_REX_OWNER_NAME = "/^[\p{L}\p{N}\s\.\,\(\)\'\p{Devanagari}]*$/u";

        $this->_REX_MOBILE_NO                               = "/[0-9]{10}/";
        $this->_ENG_MARATHI                                 = "/^[\p{Devanagari}]+$/u"; #"/^[A-Za-z0-9\x{0900}-\x{097F}\.|\-\/ ]+/gmi";

        $this->_APPLYCATION_TYPE = flipConstants($this->_TRADE_CONSTAINT["APPLICATION-TYPE"]);
        if($this->_APPLYCATION_TYPE)
        {
            $this->_APPLYCATION_TYPE = collect($this->_APPLYCATION_TYPE)->implode(",");
        }
        $this->_REX_APPLICATION_TYPE = "required|string|in:".$this->_APPLYCATION_TYPE;
    }
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }
    protected function failedValidation(Validator $validator)
    { 
        throw new HttpResponseException(
            response()->json(
                [
                    'status' => false,
                    'message' => 'The given data was invalid',
                    'errors' => $validator->errors()
                ], 
                200)
        );
    }
}
