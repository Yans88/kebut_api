<?php

namespace App\Http\Controllers;

use App\Models\Admin as Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        //

    }

    public function index(Request $request)
    {
        $per_page = (int)$request->per_page > 0 ? (int)$request->per_page : 0;
        $keyword = !empty($request->keyword) ? strtolower($request->keyword) : '';
        $sort_column = !empty($request->sort_column) ? $request->sort_column : 'name';
        $sort_order = !empty($request->sort_order) ? $request->sort_order : 'ASC';
        $page_number = (int)$request->page_number > 0 ? (int)$request->page_number : 1;
        $where = ['admin.deleted_at' => null];
		$count = 0;
		$_data = array();
        $data = null;
        if (!empty($keyword)) { 
            $_data = DB::table('admin')->where($where)
			->leftJoin('level', 'level.id_level', '=', 'admin.id_level')
			->whereRaw("LOWER(name) like '%" . $keyword . "%'")->get();            
            $count = count($_data);
        } else {
            $ttl_data = Admin::where($where)->get();
            $count = count($ttl_data);
            $per_page = $per_page > 0 ? $per_page : $count;
            $offset = ($page_number - 1) * $per_page;
            $_data = Admin::where($where)->offset($offset)
			->leftJoin('level', 'level.id_level', '=', 'admin.id_level')
			->limit($per_page)->orderBy($sort_column, $sort_order)->get();
        }
        $result = array(
            'err_code'  	=> '04',
            'err_msg'   	=> 'data not found',
            'total_data'    => $count,
            'data'      	=> null
        );
        if ($count > 0) {
			foreach($_data as $d){
				$password  = '';
				$password  = Crypt::decryptString($d->password);
				unset($d->created_by);
				unset($d->updated_by);
				unset($d->deleted_by);
				unset($d->created_at);
				unset($d->updated_at);
				unset($d->deleted_at);
				unset($d->password);				
				$d->pass = $password;
				$data[] = $d;
			}
			//$password = Crypt::decryptString($data->password);
			//unset($data->password);
                //$data->password = $password;
            $result = array(
                'err_code'  	=> '00',
                'err_msg'  		=> 'ok',
				'total_data'	=> $count,
                'data'      	=> $data
            );
        }
        return response($result);
    }

    function detail(Request $request)
    {
        $id_admin = (int)$request->id_admin;
        $where = ['admin.deleted_at' => null, 'id_operator' => $id_admin];
        
        $count = Admin::where($where)->count();
        $result = array(
            'err_code'  => '04',
            'err_msg'   => 'data not found',
            'data'      => null
        );
        if ($count > 0) {
            $data = Admin::where($where)->leftJoin('level', 'level.id_level', '=', 'admin.id_level')->first();
            $password = Crypt::decryptString($data->password);
            unset($data->password);
            $data->password = $password;
            $result = array(
                'err_code'  => '00',
                'err_msg'   => 'ok',
                'data'      => $data
            );
        }
        return response($result);
    }

   	function store(Request $request){
		$result = array();
        $tgl = date('Y-m-d H:i:s');
		$data = array();
        $id = (int)$request->id_operator > 0 ? (int)$request->id_operator : 0;
		$data = array(            
            'name'   	 => $request->name,
            'id_level' 	 => (int)$request->id_level,
            'username'   => strtolower($request->username),
        );
		if (!$this->isValidUsername($id, $request->username)) {
            $result = array(
                'err_code'  => '06',
                'err_msg'   => 'Username already exist',
                'data'      => null
            );
            return response($result);
            return false;
        }
		if ($id > 0) {
            $data += array("updated_at" => $tgl, "updated_by" => $request->operator_by);
			if (!empty($request->pass)) $data += array("password"=>Crypt::encryptString(strtolower($request->pass)));
            DB::table('admin')->where('id_operator', $id)->update($data);
        } else {
            $data += array("created_at" => $tgl, "created_by" => $request->operator_by,"password"=>Crypt::encryptString(strtolower($request->pass)));
            $id = DB::table('admin')->insertGetId($data, "id_operator");
        }

        if ($id > 0) {
            $data += array('id_operator' => $id);
            $result = array(
                'err_code'  => '00',
                'err_msg'   => 'ok',
                'data'      => $data
            );
        } else {
            $result = array(
                'err_code'  => '05',
                'err_msg'   => 'Insert has problem',
                'data'      => null
            );
        }
        return response($result);
	}

    // function edit(Request $request)
    // {
        // $tgl = date('Y-m-d H:i:s');
        // $id_admin = (int)$request->id_admin;
        // if ($id_admin > 0) {
            // $data = Admin::where('id_operator', $id_admin)->first();
            // $data->name = $request->name;
            // $data->username = $request->username;
            // if (!empty($request->pass)) $data->password = Crypt::encryptString(strtolower($request->pass));
            // $data->updated_at = $tgl;
            // $data->updated_by = $request->updated_by;
            // $data->save();
            // $result = array(
                // 'err_code'  => '00',
                // 'err_msg'   => 'ok',
                // 'data'      => $data
            // );
        // } else {
            // $result = array(
                // 'err_code'  => '02',
                // 'err_msg'   => 'id_admin required',
                // 'data'      => null
            // );
        // }
        // return response($result);
    // }

    function del(Request $request)
    {
        $tgl = date('Y-m-d H:i:s');
        $id_admin = $request->id_operator;
        //$data = Admin::where('id_operatobr', $id_admin)->first();
        // $data->deleted_at = $tgl;
        // $data->deleted_by = $request->operator_by;
		$data = array(
			'deleted_at'	=> $tgl,
			'deleted_by'	=> $request->operator_by
		);
        // $data->save();
		DB::table('admin')->where('id_operator', $id_admin)->update($data);
        $result = array(
            'err_code'  => '00',
            'err_msg'   => 'ok'
        );
        return response($result);
    }

    function login_cms(Request $request)
    {
        $username = strtolower($request->username);
        $pass = strtolower($request->pass);
        $where = ['admin.deleted_at' => null, 'username' => $username];
		
        $count = Admin::where($where)->count();
        $result = array(
            'err_code'  => '04',
            'err_msg'   => 'Username dan Password tidak sesuai',
            'data'      => null
        );
        if ($count > 0) {
			$data = Admin::where($where)->first();
            $password = Crypt::decryptString($data->password);
            if ($pass == $password) {
				unset($data->created_by);
				unset($data->updated_by);
				unset($data->deleted_by);
				unset($data->created_at);
				unset($data->updated_at);
				unset($data->deleted_at);
                unset($data->password);
                //$data->password = $password;
                $result = array(
                    'err_code'  => '00',
                    'err_msg'   => 'ok',
                    'data'      => $data
                );
            } else {
                $result = array(
                    'err_code'  => '03',
                    'err_msg'   => 'Password tidak sesuai'

                );
            }
        }
        return response($result);
    }
	
	function isValidUsername($id_operator, $username)
    {
        $where = array();
        $where = array('deleted_at' => null, 'username' => strtolower($username));
        $res_idbanner = DB::table('admin')->select('id_operator')->where($where)->first();
        $res_id = !empty($res_idbanner) ? $res_idbanner->id_operator : 0;
        if ((int)$res_id > 0 && $res_idbanner->id_operator != $id_operator) {
            return false;
        } else {
            return true;
        }
    }

    //
}
