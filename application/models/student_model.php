<?php
class Student_model extends SS_Model{
	function __construct(){
		parent::__construct();
	}

	function fetch($id){
		$id=intval($id);
		
		$query="
			SELECT * 
			FROM people
			WHERE id=$id AND company={$this->config->item('company/id')}";
		
		return $this->db->query($query)->row_array();
	}
	
	function fetchClassInfo($student_id){
		$student_id=intval($student_id);
		
		$q_student_class="
			SELECT team_people.id_in_team AS num_in_class,
				CONCAT(RIGHT(10000+team.num,4),team_people.id_in_team) AS num,
				team.num AS class,team.name AS class_name,
				people.name AS class_teacher_name
			FROM team_people
				INNER JOIN team ON team_people.team=team.id
				LEFT JOIN people ON team.leader=people.id
			WHERE team.company={$this->config->item('company/id')}
				AND team_people.people=$student_id
				AND team_people.term={$this->school->current_term}
		";
		
		return $this->db->query($q_student_class)->row_array();
	}
	
	function getList(){
		$q="
			SELECT 
				people.id,people.name AS name,people.id_card,
				student_num.num,
				team.name AS class_name
			FROM 
				people
				INNER JOIN (
					SELECT people,team,
						right((1000000 + CONCAT(team.num,right((100 + team_people.id_in_team),2))),6) AS num
					FROM team_people INNER JOIN team ON team.id=team_people.team
					WHERE team_people.term = '{$this->school->current_term}'
				)student_num ON student_num.people=people.id
				INNER JOIN team ON team.id=student_num.team
				INNER JOIN team_relationship ON team.id=team_relationship.relative
				INNER JOIN (
					SELECT id,num FROM team WHERE type='grade'
				)grade ON grade.id=team_relationship.team
			WHERE people.display=1
				AND (
					team.leader={$this->user->id}
					OR '".($this->user->isLogged('jiaowu') || $this->user->isLogged('zhengjiao') || $this->user->isLogged('health'))."'='1'
				)
		";
		//班主任可以看到自己班级的学生，教务和政教可以看到其他班级的学生
		
		//将班主任的视图定位到自己班级
		if(!option('class') && !option('grade') && isset($this->user->manage_class)){
			option('class',$this->user->manage_class['id']);
			option('grade',$this->user->manage_class['grade']);
		}
		$q=$this->addCondition($q,array('class'=>'team.id','grade'=>'grade.id'),array('grade'=>'class'));
				
		$q=$this->search($q,array('student_num.num'=>'学号','people.name'=>'姓名'));
		
		$q=$this->orderby($q,'student_num.num','ASC',array('student_num.num','student.name'));
		
		$q=$this->pagination($q);
		
		return $this->db->query($q)->result_array();
	}
	
	/**
	 * 获得一个学生的家庭成员列表
	 */
	function getRelativeList($student_id){
		$student_id=intval($student_id);
		
		$query="
			SELECT 
				id,name,relationship,work_for,contact.content AS contact
			FROM 
				people
				INNER JOIN (
					SELECT people,content FROM people_profile WHERE name='手机'
				)contact
				INNER JOIN people_relationship ON people_relationship.relative=people.id AND people_relationship.relation_type='家庭成员'
			WHERE people_relationship.people=$student_id
		";
		
		return $this->db->query($query)->result_array();
	}
	
	/**
	 * 获得一个学生的奖惩记录列表
	 */
	function getBehaviourList($student_id,$limit=5){
		$student_id=intval($student_id);

		$query="
			SELECT name,type,date,level,content FROM student_behaviour WHERE student = $student_id
			LIMIT $limit
		";
		
		return $this->db->query($query)->result_array();
	}
	
	function getCommentList($student_id){
		$student_id=intval($student_id);

		$query="
			SELECT student_comment.title,student_comment.content,
				FROM_UNIXTIME(time,'%Y-%m-%d') AS time,IF(staff.name IS NULL,student_comment.username,staff.name) AS username 
			FROM student_comment LEFT JOIN staff ON staff.id=student_comment.uid 
			WHERE student = '{$student_id}' AND (reply_to IS NULL OR reply_to = '{$this->user->id}' OR uid = '{$this->user->id}')
			ORDER BY student_comment.time DESC
			LIMIT 5
		";
		
		return $this->db->query($query)->result_array();
	}
	
	/**
	 * 家校互动页面学生评价留言列表
	 * TODO跟上面的getCommentList合并兼容
	 */
	function getInteractiveList(){
		$student_id=intval($student_id);

		$query="
			SELECT student_comment.title,student_comment.content,
				FROM_UNIXTIME(student_comment.time,'%Y-%m-%d') AS date,student_comment.username,student_comment.student,
				view_student.name AS student_name
			FROM student_comment INNER JOIN view_student ON student_comment.student=view_student.id
			WHERE student_comment.reply_to='{$this->user->id}' 
				OR student_comment.uid='{$this->user->id}' 
				OR (
					'".isset($_SESSION['manage_class'])."' 
					AND view_student.class='{$_SESSION['manage_class']['id']}'
				)
			ORDER BY time DESC
		";
		
		$query=$this->pagination($query);
		
		return $this->db->query($query)->result_array();
	}
	
	function update($student_id=NULL){
		db_query("DROP TABLE IF EXISTS view_student");
		db_query("
			CREATE TABLE view_student
			SELECT 
				student.id AS id,student.gender,student.name AS name,student.type AS type,student.id_card AS id_card,student.extra_course,
				right((1000000 + concat(student_class.class,right((100 + student_class.num_in_class),2))),6) AS num,
				class.id AS class,class.name AS class_name,class.depart AS depart,
				grade.id AS grade,grade.name AS grade_name 
			FROM 
				student 
				INNER JOIN student_class ON student.id = student_class.student
				INNER JOIN class ON student_class.class = class.id
				INNER JOIN grade ON grade.id = class.grade
			WHERE
				student_class.term = '".$this->school->current_term."'
			ORDER BY num
		");
		db_query("ALTER TABLE  `view_student` ADD PRIMARY KEY (  `id` )");
		db_query("ALTER TABLE  `view_student` ADD INDEX (type)");
		db_query("ALTER TABLE  `view_student` ADD INDEX (num)");
		db_query("ALTER TABLE  `view_student` ADD INDEX (class)");
		db_query("ALTER TABLE  `view_student` ADD INDEX (grade)");
		db_query("ALTER TABLE  `view_student` ADD INDEX (depart)");
		db_query("ALTER TABLE  `view_student` ADD INDEX (extra_course)");
		db_query("ALTER TABLE  `view_student` ADD FOREIGN KEY (  `id` ) REFERENCES  `starsys`.`student` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE");
	}
	
	function changeClass($student_id,$old_class_id,$new_class_id){
		if($old_class_id!=$new_class_id){
			$new_num_in_class=db_fetch_field("SELECT MAX(num_in_class)+1 FROM student_class WHERE class='".$new_class_id."' AND term='".$this->school->current_term."'");
			
			$this->db->update('student_class',array('num_in_class'=>$new_num_in_class,'class'=>$new_class_id),"student = '".$student_id."' AND class = '".$old_class_id."' AND term = '".$this->school->current_term."'");
			$new_student_num=$new_class_id.substr($new_num_in_class+100,-2);
			
			student_update($student_id);
			
			return $new_student_num;
	
		}else{
			return false;
		}
	}
	
	function addRelatives($student,$relative_data){
		$relatives=array(
			'student'=>$student,
			'name'=>$relative_data['name'],
			'relationship'=>$relative_data['relationship'],
			'contact'=>$relative_data['contact'],
			'work_for'=>$relative_data['work_for']
		);
		
		$relatives+=uidTime();
		
		return db_insert('student_relatives',$relatives);
	}
	
	function addBehaviour($student,$data){
		$behaviour=array(
			'student'=>$student,
			'name'=>$data['name'],
			'date'=>$data['date'],
			'type'=>$data['type'],
			'level'=>$data['level'],
			'content'=>$data['content']
		);
		
		$behaviour+=uidTime();
		
		return db_insert('student_behaviour',$behaviour);
	}
	
	function addComment($student,$data){
		$field=array('title','content','reply_to');
		foreach($data as $key => $value){
			if(!in_array($key,$field)){
				unset($data[$key]);
			}
		}
		
		$data['student']=$student;
		
		$data+=uidTime();
		
		return db_insert('student_comment',$data);
	}
	
	function deleteRelatives($student_relatives){
		$condition = db_implode($student_relatives, $glue = ' OR ','id','=',"'","'", '`','key');
		db_delete('student_relatives',$condition);
	}
	
	function getScores($student){
		$student=intval($student);

		$query="SELECT exam_name,course_1,course_2,course_3,course_4,course_5,course_6,course_7,course_8,course_9,course_10,course_sum_3,course_sum_5,course_sum_8,rank_1,rank_2,rank_3,rank_4,rank_5,rank_6,rank_7,rank_8,rank_9,rank_10,rank_sum_3,rank_sum_5,rank_sum_8
			FROM view_score WHERE student = '".$student."'
		ORDER BY exam DESC";
	
		return $this->db->query($query)->result_array();
	}
	
	function testClassDiv($div,$data,$classes,$gender,$showResult=false){
		global $tests,$students,$subjects;
	
		$tests++;
		
		$score=array();
		/*$score:array(
			1(性别)=>array(
				1(班号)=>array(
					1(科目号)=>array(
						学号=>本科分数
					)
				)
			)
		)
		*/
	
		//将div分班方案分解为score分数表
		for($subject=0;$subject<$subjects;$subject++){
			foreach($div as $gender_in_array1 => $array1){
				foreach($array1 as $class=>$array2){
					foreach($array2 as $student){
						$score[$gender_in_array1][$class][$subject][$student]=$data[$student][$subject];
					}
				}
			}
		}
		
		//$_SESSION['score']=$score;
		//print_r($score);
		
		$result=array();
	
		for($subject=0;$subject<$subjects;$subject++){
			for($class=0;$class<$classes;$class++){
				$result[$class][$subject]['num']=count($score[$gender][$class][$subject]);//得到每班每学科的人数
				$result[$class][$subject]['sum']=array_sum($score[$gender][$class][$subject]);//得到每班每学科的和
				$result[$class][$subject]['aver']=$result[$class][$subject]['sum']/$result[$class][$subject]['num'];//得到每班每学科的平均值
				//$result[$class][$subject]['std']=std($score[$gender][$class][$subject],$result[$class][$subject]['aver']);//得到每班每学科的标准差
			}
		}
		
		if($showResult){
			echo "\n<br>result".$gender.": "; print_r($result);
		}
		
		/*for($subject=0;$subject<$subjects;$subject++){
			for($class=0;$class<$classes;$class++){
		
				$std[]=$result[$class][$subject]['std'];
		
			}
		}
		
		$std_sum=array_sum($std);//各班各学科的标准差的和*/
		
		$aver_std=array();
	
		for($subject=0;$subject<$subjects;$subject++){
	
			$aver=array();
	
			for($class=0;$class<$classes;$class++){
				$aver[]=$result[$class][$subject]['aver'];
			}
			$aver_std[]=std($aver);
		}
		
		$aver_std_sum=array_sum($aver_std);//各班每学科总分的标差的和
		
		return $aver_std_sum;
	}
	
	function getIdByParentUid($parent_uid){
		return db_fetch_field("SELECT id FROM student WHERE parent = '".$parent_uid."'");
	}
}