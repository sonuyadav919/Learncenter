<?php

use App\Models\School;
use App\User;

use App\Http\Controllers\MetricsloginController;

class SiteHelpers2
{

	public static function getCSCIDashbordPermission()
	{
			$mySchools = self::getMySchool();

			$permission = 0;
			$user = Auth::user();

			$schoolPermission = School::join('survey_category', 'survey_category.id', '=', 'schools.survey_to_be_taken')
																	->whereIn('schools.id', $mySchools)->select('schools.*', 'survey_category.name as category')->get();

			foreach($schoolPermission as $permi)
			{
						if(strtolower($permi->category) == 'csci')
							$permission = 1;
						elseif($user->group_id == 1)
							$permission = 1;
			}

			return $permission;
	}


	public static function getMySchool()
	{
			$schools = [];
			$user = Auth::user();

			if($user->group_id == 1)
				$schools = School::lists('id')->toArray();
			elseif($user->group_id == 2)
				$schools = \DB::table('support_agent_schools')->where('user_id',  $user->id)->lists('school_id');
			elseif($user->group_id == 3)
				$schools[] = $user->school_id;
			elseif($user->group_id == 4)
				$schools = \DB::table('regional_admin_schools')->where('user_id',  $user->id)->lists('school_id');
			elseif($user->group_id == 5)
				$schools[] = $user->school_id;
			elseif($user->group_id == 9)
				$schools = \DB::table('coach_schools')->where('user_id',  $user->id)->lists('school_id');

			return array_filter($schools);
	}

	public static function saveLoginMetrics($function){

		 $user = \Auth::user();
		 if(\Auth::check() && !\Session::has('session_admin')){

			 if(substr($user->last_login, 0, strpos($user->last_login, " "))<date('Y-m-d')){
				 \Auth::logout();
				 return;
			 }

			 $metricsLogin=new MetricsloginController();
			 $metricsLogin->$function();
		 }
		 else
		  return 'false';
	}

	public static function convert_time($time){

		$tokens = array (
				2592000 => 'Year',
				604800 => 'Month',
				86400 => 'Week',
				3600 => 'Day',
				60 => 'Hour',
				1 => 'Minute'
			);

	return	self::cal_time($tokens,$time);

	}



	public static function getSchoolUsersLists($schoolsId, $groupIds=[])
	{
			$users = User::leftJoin('schools', 'schools.id', '=', 'tb_users.school_id')
											->leftJoin('support_agent_schools', 'support_agent_schools.user_id', '=', 'tb_users.id')
											->leftJoin('regional_admin_schools', 'regional_admin_schools.user_id', '=', 'tb_users.id')
											->leftJoin('coach_schools', 'coach_schools.user_id', '=', 'tb_users.id')
											->whereNotIn('tb_users.group_id', [6,7,8]);

											if(count($groupIds)){
												$users = $users->whereIn('tb_users.group_id', $groupIds);
											}

			 $users = $users->whereIn('tb_users.school_id', $schoolsId)
											->orWhereIn('support_agent_schools.school_id', $schoolsId)
											->orWhereIn('regional_admin_schools.school_id', $schoolsId)
											->orWhereIn('coach_schools.school_id', $schoolsId)
											->groupBy('tb_users.id')
											->lists('tb_users.id')->toArray();

				return $users;
	}


	public static function getDatesFromRange($start, $end, $format = 'Y-m-d')
  {
      $array = array();
      $interval = new DateInterval('P1D');

      $realEnd = new DateTime($end);
      $realEnd->add($interval);

      $period = new DatePeriod(new DateTime($start), $interval, $realEnd);

      foreach($period as $date) {
          $array[] = $date->format($format);
      }

      return $array;
  }


	private static function cal_time($tokens,$time){
		foreach ($tokens as $unit => $text) {
				if ($time < $unit) continue;
				$numberOfUnits = floor($time / $unit);

				$extra_time = $time - $numberOfUnits * $unit;

				if($extra_time>0){
          foreach($tokens as $unit2 => $text2){
						if ($extra_time < $unit2) continue;
						$numberOfUnits2 = floor($extra_time / $unit2);
					}
					$secound_cal_time = $numberOfUnits2.' '.$text2.(($numberOfUnits2>1)?'s':'');
				}

				return $numberOfUnits.' '.$text.(($numberOfUnits>1)?'s':'').' '.((isset($secound_cal_time))?$secound_cal_time:'');
		}
	}

	public static function addCustomTableGrid($fields){

    foreach($fields as $key => $field)
    $data[] = ['field'=>$field,'label'=>$field,'view'=>1,'sortlist'=>++$key,'sortable'=>1,'attribute'=>['hyperlink'=>['active'=>1],'image'=>['active'=>0,'path'=>'','size_x'=>'','size_y'=>'','html'=>'']]];

    return $data;
  }


}
