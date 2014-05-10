<?

require_once '../inc/sql.php';
require_once '../inc/api.php';

class Ios_api {

    public function getJobs() { //340 402
        $query = "select gid as 'id', gname as 'title' from pages where cat = 106 AND popular = 1";
        $jobs = $this->getData($query);
    foreach($jobs as $k => $v){
        $jobs[$k]['title'] = html_entity_decode($jobs[$k]['title']);
        }

        return json_encode(array('jobs' => $jobs));
    }

    public function getWorks() { //288746 6957rows 322200 7004rows
//         $query = "select gid as 'id', gname as 'title' from pages where gid IN (SELECT gid FROM `page_categories` WHERE `cat` = '113') or type = 1388";
        $query = "
            SELECT
                pages.gid AS id, gname AS title
            FROM
            pages NATURAL
            JOIN (
                (SELECT
                    gid
                FROM
                    pages
                WHERE pages.privacy = 0
                AND (TYPE = 1388))
                ) AS gids
            LEFT JOIN photos
                ON photos.id = pid
            LEFT JOIN users
                ON photos.uid = users.uid
            INNER JOIN categories
                ON categories.cat = pages.cat
            GROUP BY pages.gid
        ";
        $works = $this->getData($query);
    foreach($works as $k => $v){
        $works[$k]['title'] = html_entity_decode($works[$k]['title']);
        }

        return json_encode(array('works' => $works));
    }

    public function getSectors() {   //269 271
        $query = "SELECT cat AS 'id', catname AS 'title' FROM categories WHERE cattype = 'G' AND industry = 1388 ORDER BY catname";
        $sectors = $this->getData($query);
        foreach($sectors as $k => $v){
        $sectors[$k]['title'] = html_entity_decode($sectors[$k]['title']);
        }

        return json_encode(array('sectors' => $sectors));
    }

    public function getVessels($offset = 0) {

//         $query = "
//             SELECT
//                 pages.gid, gname AS title
//             FROM
//             pages NATURAL
//             JOIN (
//                 (SELECT
//                     gid
//                 FROM
//                     pages
//                 WHERE pages.privacy = 0
//                 AND (TYPE = 1389))
//                 ) AS gids
//             LEFT JOIN photos
//                 ON photos.id = pid
//             LEFT JOIN users
//                 ON photos.uid = users.uid
//             INNER JOIN categories
//                 ON categories.cat = pages.cat
//             GROUP BY pages.gid
//         ";
        $query = "
            SELECT
                pages.gid AS id, gname AS title
            FROM
            pages NATURAL
            JOIN (
                (SELECT
                    gid
                FROM
                    pages
                WHERE pages.privacy = 0
                AND (TYPE = 1389))
                ) AS gids

            INNER JOIN categories
                ON categories.cat = pages.cat
        ";
        $query .= $offset > 0 ? "LIMIT $offset , 10000" : "LIMIT 10000";
        $vessels = $this->getData($query);
        foreach($vessels as $k => $v){
        $vessels[$k]['title'] = html_entity_decode($vessels[$k]['title']);
        }

        return json_encode(array('vessels' => $vessels));
    }

    public function setVessels() {
        $works = json_decode($_POST['json_works'], true);
//        $savePrev = $_POST['savePrev'];
//        if (isset($savePrev) && $savePrev == 1)
//            $savePrev = true;
//        else
//            $savePrev = false;
//        $result = FALSE;

        $uid = $_POST['uid'];
//if( !$savePrev )
        mysql_query("delete from work where uid={$uid}");

//print_r($works);

        foreach ($works as $w) {

            if (intval($w['month-0']) == 0)
                $w['month-0'] = 1;

            if (intval($w['year-0']) == 0)
                $w['year-0'] = date("Y");

            $w['start'] = intval($w['year-0']) . "/" . intval($w['month-0']) . "/1 00:00:00";
            $w['stop'] = intval($w['year-1']) . "/" . intval($w['month-1']) . "/1 00:00:00";

            if ($w['present'] == 1 || strtotime($w['start']) > strtotime($w['stop']))
                $w['stop'] = '0000-00-00 00:00:00';

            foreach (array("occupation", "employer") as $f) {
                // Check if value is id
                if ($w[$f]['val'])
                    $w[$f]['val'] = intval($w[$f]['val']);
                // If value is name and present
                elseif ($w[$f]['txt']) {
                    $name = trim(addslashes($w[$f]['txt']));

                    $type = PAGE_TYPE_BUSINESS;
                    if ($f == "occupation")
                        $type = PAGE_TYPE_PROFESSIONAL;
                    else if ($w['isvessel'] == 'on' || $w['isvessel'] == 'checked')
                        $type = PAGE_TYPE_VESSEL;
                    // Searching id of vessel by name
                    $gid = quickQuery("select gid from pages where gname='$name' and type='$type' and privacy=0");
                    // Vessel in db
                    if ($gid)
                        $w[$f]['val'] = $gid;
                    else {
                        // If occupation is not in db create it
                        if ($f == "occupation") {
                            mysql_query("insert into pages (gname,type,cat) values ('$name','" . PAGE_TYPE_PROFESSIONAL . "'," . CAT_PROFESSIONS . ")");
                            $w[$f]['val'] = mysql_insert_id();
                        } else {
                            // If employer is not in db create it
                            $type = PAGE_TYPE_BUSINESS;
                            if ($w['isvessel'] == 'on' || $w['isvessel'] == 'checked')
                                $type = PAGE_TYPE_VESSEL;
                            mysql_query("insert into pages (gname,type) values ('$name','" . $type . "')");
                            $w[$f]['val'] = mysql_insert_id();
                        }
                    }
                }
                else {
                    continue;
                }

                if (quickQuery("select count(*) from page_members where gid='" . $w[$f]['val'] . "' and uid='" . $uid . "'") == 0) {
                    mysql_query("insert into page_members (gid,uid) values ({$w[$f]['val']},{$uid})");

//                    if (mysql_insert_id())
//                        $API->feedAdd("G", $w[$f]['val'], null, null, $w[$f]['val']);
                }

                $gids[$f][] = $w[$f]['val'];
            }

            $work['uid'] = $uid;
            $work['start'] = $w['start'];
            $work['stop'] = $w['stop'];
            $work['descr'] = addslashes(urldecode($w['descr']));
            $work['www'] = addslashes(htmlentities($w['www']));
            $work['employer'] = $w['employer']['val'];
            $work['occupation'] = $w['occupation']['val'];

            $q = "insert into work (" . implode(",", array_keys($work)) . ") values ('" . implode("','", $work) . "')";
            mysql_query($q);
        }
        echo "{'result':'success'}";
    }

    private function getEmployerId($param, $isvessel) {

        $name = trim(addslashes($param));

        $type = PAGE_TYPE_BUSINESS;
        if ($isvessel)
            $type = PAGE_TYPE_VESSEL;
        // Searching id of vessel by name
        $gid = quickQuery("select gid from pages where gname='$name' and type='$type' and privacy=0");
        // Vessel in db
        if ($gid)
            $w[$f]['val'] = $gid;
        // Employer not present - let's create it!
        else {
            mysql_query("insert into pages (gname,type) values ('$name','" . $type . "')");
            $gid = mysql_insert_id();
        }

        if (quickQuery("select count(*) from page_members where gid='" . $gid . "' and uid='" . $uid . "'") == 0) {
            mysql_query("insert into page_members (gid,uid) values ({$gid},{$uid})");
        }
        return $gid;
    }

    public function getVesselsExtended($search_str) {
//         $query = "SELECT
//                 pages.gname
//                 FROM pages
//                 WHERE
//                 privacy = 0
//                 AND
//                 TYPE = 1389
//         ";
//         $query .= $offset > 0 ? "LIMIT $offset , 10000" : "LIMIT 10000" ;
//         $query .= $offset > 0 ? "LIMIT $offset , 30000" : "LIMIT 30000" ;

        $query = "
            SELECT
                pages.gid AS id, pages.gname AS title, boats.length AS length_ft, boats.length*0.3048 AS length_m
            FROM
                pages LEFT JOIN boats ON pages.gid = boats.gid
            WHERE
                pages.gname LIKE '%$search_str%'
            AND
                pages.type = 1389
            AND
                pages.privacy = 0
            ";

        $vessels = $this->getData($query);
        //         $vessels = array_unique($vessels);

        return json_encode(array('vessels' => $vessels));
    }

    public function getVesselsExtended1($search_str) {
        $q = $search_str;
    //                 $cats = array();
    //
    //                 $include_pages = true;
    //                 $select_pages['exnames'] = "trim(exnames)";
    //                 $select_pages['length_ft'] = "length";
    //                 $select_pages['length_m'] = "(length*0.3048)";
    //                 $types[] = 1389;
    //                 $union[] = "select pages.gid from boats as boats inner join pages on boats.id=pages.glink where name != '' and exnames like '%{$q}%'";
    //                 $join[] = "LEFT JOIN boats as boats ON glink=boats.id";
    //
    //                 array_unshift($union, "SELECT gid from pages where gname LIKE '%{$q}%' AND pages.privacy=" . 0 . " and (" .
    //                                         (count($types) > 0 ? "type=" . implode(' or type=', $types) . (count($cats) > 0 ? ' or ' : '') : '') .
    //                                             (count($cats) > 0 ? " cat=" . implode(' or cat=', $cats) : "") . "
    //                                             )");
    //
    //                 $sel_pages = '';
    //                 if (count($select_pages) > 0)
    //                     foreach ($select_pages as $k => $v)
    //                         $sel_pages .= ",$v as $k";
    //
    //                 $query[] = "SELECT pages.cat,pages.gid,type,users.container_url,hash,gname as title,catname as cat_str{$sel_pages}
    //                         FROM pages
    //                         natural join (
    //                             (" . implode(")\n\t\t\t\t\tunion (", $union) . ")
    //                         ) as gids
    //                         " . implode ("\n", $join) . "
    //                         LEFT JOIN photos ON photos.id=pid
    //                         LEFT JOIN users ON photos.uid=users.uid
    //                         inner join categories on categories.cat=pages.cat
    //                         group by pages.gid";
    //
    //                 $vessels = $this->getData($query[0]);

        $query = "
                SELECT
                pages.gname
                FROM pages
                WHERE
                gname LIKE '%$search_str%'
                AND
                type = 1389
                AND
                privacy = 0

                ";
        $query = "
                SELECT
                pages.gid AS id, pages.gname AS title, boats.length AS length_ft, boats.length*0.3048 AS length_m
                FROM pages LEFT JOIN boats ON pages.gid = boats.gid
                WHERE
                pages.gname LIKE '%$search_str%'
                AND
                pages.type = 1389
                AND
                pages.privacy = 0

                ";
        $vessels = $this->getData($query);


        //         $vessels = array_unique($vessels);
        //                 $str = '';
        //                 foreach($vessels as $key => $value) {
        //                     $str = $str . $value['gname'] . ',';
        //                 }
        //                 $str = rtrim($str, ",");

        return json_encode(array('vessels' => $vessels));


        //         $ch = curl_init('http://dev.salthub.com/ajax/get_vessel.php');
        //         curl_setopt($ch, CURLOPT_POSTFIELDS, 'q=GEORG+OTS&n=100&guid=f5443e2e-4073-50b0-9ec-7500e7114b6');
        //         curl_exec($ch); // выполняем запрос curl - обращаемся к сервера php.su
        //         curl_close($ch);
    }

    public function getUser($data) {
        $data = json_decode($data, true);
        $id =  (int) $data[0]['id'];

        $query = "SELECT
                users.password as 'pass',
                users.uid,
                users.name as 'username',
                users.email,
                users.public_email,
                users.cell AS 'phone',
                users.public_phone AS 'home_phone',
                users.www AS 'web',
                categories.catname AS 'sector',
                users.occupation as 'job',
                users.company as 'work',
                users.work_on_vessel,
                CONCAT(users.container_url, '/', photos.hash, '.jpg') AS photo
            FROM users
            LEFT JOIN categories ON categories.cat = users.sector
            LEFT JOIN photos ON users.pic = photos.id
            WHERE users.uid = {$id} LIMIT 1";

        $result = $this->getData($query);
//         print_r($result); //die;

        if ($result == null)
            $result = null;
        else {
    //             $result[0]['photo'] = $this->getUserPhoto($result[0]['uid']);
            $result[0]['url'] = $_SERVER['HTTP_HOST'] .'/user/_'. $result[0]['uid'];
            foreach($result[0] as $k => $v){
                $result[0][$k] = html_entity_decode($result[0][$k]);
            }
            $result[0]['skills'] = $this->getSkills($result[0]['uid']);

            $employer_id = $this->getEmployerId($result[0]['work'], null);

    //             $query = " SELECT wid as work_id, start as work_start, stop as work_stop FROM work WHERE uid = $id ORDER BY wid DESC LIMIT 1 ";
    //             $query = " SELECT wid as work_id, start as work_start, stop as work_stop FROM work WHERE uid = {$id} AND employer = {$employer_id}  ";
            $query = " SELECT wid as work_id, start as work_start, stop as work_stop FROM work WHERE uid = {$id} ORDER BY wid DESC LIMIT 1 ";
            $work = $this->getData($query);

            $work[0]['work_start'] = $this->dateConvert($work[0]['work_start']);            //    Ворки Вынести в отдельный метод или добавить в getUserInfo
            $work[0]['work_stop'] = $this->dateConvert($work[0]['work_stop']);
            $work[0]['current_work'] = ($work[0]['work_stop'] == '00-00-0000') ? true : false ;

            foreach($work[0] as $k => $v) {
                $result[0][$k] = $v;
            }
        }
        // print_r($result); die;
        return json_encode(array('user' => $result[0]));
    }

    private function getUserInfo($id) {
        $query = "SELECT users.uid, users.name as 'username', users.public_email, users.show_public_email, users.cell AS 'phone', users.public_phone AS 'home_phone', users.www AS 'web', categories.catname AS 'sector', users.occupation as 'job', users.company as 'work', users.username AS url
                FROM users
                LEFT JOIN categories ON categories.cat = users.sector
                WHERE uid = " . $id;

        $result = $this->getData($query);

        foreach ($result as $k => $v) {
            if( $v['show_public_email'] ) {
                unset($result[$k]['show_public_email']);
            }
            else {
                $result[$k]['public_email'] = '';
                unset($result[$k]['show_public_email']);
            }
        }

        if ($result == null)
            $result = null;
        else {
            $result[0]['photo'] = $this->getUserPhoto($id);
            $result[0]['skills'] = $this->getSkills($id);
            $result[0]['url'] = $_SERVER['HTTP_HOST'] .'/user/_'. $id;
        }

        return $result[0];
    }

        private function getUserInfo2($id) {
        $query = "SELECT
                    users.uid,
                    users.name as 'username',
                    users.email,
                    users.public_email,
                    users.show_public_email,
                    users.cell AS 'phone',
                    users.public_phone AS 'home_phone',
                    users.www AS 'web',
                    categories.catname AS 'sector',
                    users.occupation as 'job',
                    users.company as 'work',
                    users.username AS url
                FROM users
                LEFT JOIN categories ON categories.cat = users.sector
                WHERE uid = " . $id;

        $result = $this->getData($query);
         //print_r($result); die;
        if ($result == null)
            return 0;
        else {
            $result[0]['show_public_email'] = (bool) $result[0]['show_public_email'];
            $result[0]['photo'] = $this->getUserPhoto($result[0]['uid']);
            foreach($result[0] as $k => $v){
                $result[0][$k] = html_entity_decode($result[0][$k]);
            }
            $result[0]['skills'] = $this->getSkills($result[0]['uid']);
            $result[0]['url'] = $_SERVER['HTTP_HOST'] .'/user/_'. $result[0]['uid'];

            $query = " SELECT wid as work_id, start as work_start, stop as work_stop FROM work WHERE uid = {$result[0]['uid']} ORDER BY wid DESC LIMIT 1 ";
            $work = $this->getData($query);

            $work[0]['work_start'] = $this->dateConvert($work[0]['work_start']);
            $work[0]['work_stop'] = $this->dateConvert($work[0]['work_stop']);
            $work[0]['current_work'] = ($work[0]['work_stop'] == '00-00-0000') ? true : false ;
            unset($work[0]['work_id']);
//             print_r($work);
            foreach($work[0] as $k => $v) {
                $result[0][$k] = $v;
            }
            $q = "UPDATE users SET `lastlogin` = '".date('Y-m-d H:i:s', time())."' WHERE `uid` = {$result[0]['uid']}  LIMIT 1";
            mysql_query($q);
        }

        return $result[0];
    }

    public function getFriends($data) {
        $data = json_decode($data, true);
        $user =  (int) $data[0]['id'];

        $query = "SELECT uid as 'id' FROM friends INNER JOIN users ON IF(id1 = " . $user . ", id2, id1) = users.uid  WHERE (id1 = " . $user . " OR id2 = " . $user . ") AND active = 1 AND STATUS = 1";
        $result = $this->getData($query);

        $friends = array();
        if ($result != null) {
            foreach ($result as $key => $value) {
            $friends[] = $this->getUserInfo($value['id']);
            }
        } else
            $friends[0] = 'not found';

        return json_encode(array('friends' => $friends));
    }

    public function checkUser($data) {
        $data = json_decode($data, true);
        $query = "select uid from users where email = '" . $data[0]['email'] . "'";

        $result = $this->getData($query);
        if ($result == null)
            $return = false;
        else
            $return = true;

        return json_encode(array('result' => $return));
    }

    /*
     *[{"login": "anarchy92@mail.ru","pass": "123123123"}]
     */

    public function loginUser($data) {
        $data = json_decode($data, true);
        $login = $data[0]['login'];
        $pass = $data[0]['pass'];
        if(!isset($login, $pass))
            return json_encode(0);
        $query = "SELECT
                    users.uid,
                    users.name as 'username',
                    users.email,
                    users.public_email,
                    users.show_public_email,
                    users.cell AS 'phone',
                    users.public_phone AS 'home_phone',
                    users.www AS 'web',
                    categories.catname AS 'sector',
                    users.occupation as 'job',
                    users.company as 'work',
                    users.username AS url
                FROM users
                LEFT JOIN categories ON categories.cat = users.sector
                WHERE email = '{$login}' AND password = '" . md5($pass) . "'";

/*             CONCAT(users.container_url, '/', photos.hash, '.jpg') AS photo
            LEFT JOIN photos ON users.pic = photos.id

    //             $result[0]['photo'] = $this->getUserPhoto($result[0]['uid']);
            $result[0]['url'] = $_SERVER['HTTP_HOST'] .'/user/_'. $result[0]['uid'];
*/
        $result = $this->getData($query);
//         print_r($result); die;
        if ($result == null)
            return json_encode(0);
        else {
            $result[0]['show_public_email'] = (bool) $result[0]['show_public_email'];
            $result[0]['photo'] = $this->getUserPhoto($result[0]['uid']);
            foreach($result[0] as $k => $v){
                $result[0][$k] = html_entity_decode($result[0][$k]);
            }
            $result[0]['skills'] = $this->getSkills($result[0]['uid']);
            $result[0]['url'] = $_SERVER['HTTP_HOST'] .'/user/_'. $result[0]['uid'];

            $query = " SELECT wid as work_id, start as work_start, stop as work_stop FROM work WHERE uid = {$result[0]['uid']} ORDER BY wid DESC LIMIT 1 ";
            $work = $this->getData($query);

            $work[0]['work_start'] = $this->dateConvert($work[0]['work_start']);
            $work[0]['work_stop'] = $this->dateConvert($work[0]['work_stop']);
            $work[0]['current_work'] = ($work[0]['work_stop'] == '00-00-0000') ? true : false ;
            unset($work[0]['work_id']);
//             print_r($work);
            foreach($work[0] as $k => $v) {
                $result[0][$k] = $v;
            }
            $q = "UPDATE users SET `lastlogin` = '".date('Y-m-d H:i:s', time())."' WHERE `uid` = {$result[0]['uid']}  LIMIT 1";
            mysql_query($q);
        }

        return json_encode(array('user' => $result));
    }

    /*
      [{"home_phone":"553627","sector":"1310","phone":"3355447","pass":"qq","web":"www","email":"seregsdfsdfa","job":"title","work":"place","username":"serega", "skills":{"0":"first", "1":"second", "2":"third"}}]

      [{"username": "john","job": "werwer", "work": "dfgdg", "sector": "111","phone": "10101010","email": "anarchy92@mail.ru","pass": "123123123"}]
      [{"0":"first", "1":"second", "2":"third"}]
     */

    public function regUser($data, $skills = null) {
        $data = json_decode($data, true);
        // print_r($data); die;
        $name = $data[0]['username'];
        $job = addslashes($data[0]['job']);
        $sector = $data[0]['sector'];
        $phone = $data[0]['phone'];
        $email = $data[0]['email'];
        $public_email = $data[0]['public_email'];
        $show_public_email = (bool) $data[0]['show_public_email'];
        $pass = md5($data[0]['pass']);

        if (isset($data[0]['work']))
            $work = addslashes($data[0]['work']);

        if (isset($data[0]['vessel']))
            $work = $data[0]['vessel'];

        //      if (isset($data[0]['skills']))
        //          $skills = $data[0]['skills'];

        if (isset($data[0]['home_phone']))
            $home_phone = $data[0]['home_phone'];
        else
            $home_phone = '';

        if (isset($data[0]['web']))
            $web = $data[0]['web'];
        else
            $web = '';

        if (isset($data[0]['birth_date']) && !empty($data[0]['birth_date'])){
            $birth_date = $this->dateConvert($data[0]['birth_date']);
        }
        else
            $birth_date = "NULL";

        $checkUser = "SELECT users.uid, users.name as 'username', users.email, users.public_email, users.show_public_email, users.dob AS birth_date, users.cell AS 'phone', users.public_phone AS 'home_phone', users.www AS 'web', categories.catname AS 'sector', users.occupation as 'job', users.company as 'work', users.username AS url
                    FROM users
                    LEFT JOIN categories ON categories.cat = users.sector
                    WHERE email = '" . $email . "' LIMIT 1";
        $result = $this->getData($checkUser);

        // if user is not exists
        if ($result == null) {
            $time = date('Y-m-d H:i:s');
//             $query = "insert into users(name, username, password, email, public_email, show_public_email, dob, cell, sector, active, verify, occupation, company, public_phone, www, joined,lastlogin) values('" . $name . "', '" . $name . "', '" . md5($pass) . "', '" . $email . "', '" . $public_email . "', '" . $show_public_email . "', '" . $birth_date . "' , '" . $phone . "', '" . $sector . "', 1, 1, '" . $job . "', '" . $work . "', '" . $home_phone . "', '" . $web . "', '" . date('Y-m-d H:i:s') . "')";
            $query = "insert into users(name, username, password, email, public_email, show_public_email, dob, cell, sector, active, verify, occupation, company, public_phone, www, joined,lastlogin) values('{$name}', '{$name}', '{$pass}', '{$email}', '{$public_email}', '{$show_public_email}', '{$birth_date}' , '{$phone}', '{$sector}', 1, 1, '{$job}', '{$work}', '{$home_phone}', '{$web}', '{$time}', '{$time}')";
        // print_r($query); die;
            $result = mysql_query($query);
            // if insert success
            if ($result == true) {
                $id = mysql_insert_id();

                if (isset($data[0]['skills']))
                    $this->setSkills($id, $data[0]['skills']);

                $result = $this->getData($checkUser);
                $result[0]['birth_date'] = $this->dateConvert($result[0]['birth_date']);
            }
            else {
                return json_encode(array('error' => 'insert to users'));
            }
        }

        $result[0]['skills'] = $this->getSkills($result[0]['uid']);
        $result[0]['url'] = $_SERVER['HTTP_HOST'] .'/user/_'. $result[0]['uid'];

        return json_encode(array('user' => $result));
    }

    public function setSkills($id, $skills) {
//         print_r($skills); die;
        ksort($skills);
        $id =  (int) $id;
        $data = implode(chr(2), $skills);
        $query = "update users set contactfor = '" . $data . "' where uid = " . $id;
        $result = mysql_query($query);

        return $result;
    }

    // [{"id":"12062", "username":"john", "pass":"123123123", "email":"anarchy92@mail.ru", "public_email":"anarchy99@mail.ru", "phone":"000000000", "home_phone":"1111111111", "sector":"1310", "web":"newsite.com", "job":"new job", "work":"new work", "skills":{"0":"new first skill", "1":"new second skill", "2":"new third skill", "3":"new fourth skill", "4":"new fifth skill"}}]
    // [{"id":"12080","username":"Arty","pass":"123","email":"elitearty@gmail.com","public_email":"rack6@rambler.ru","show_public_email":true,"phone":"3386574","home_phone":"","web":"","sector":"1310","web":"newsite.com","job":"dev-oper","work":"drag","skills":{"0":"new first skill"}}]
    public function update($data, $photo) {
        // echo var_dump(urldecode($data));
        $data = json_decode($data, true);
        // print_r($data[0]); die;
        $id =  (int) $data[0]['id'];
        $name = $data[0]['username'];
        $pass = $data[0]['pass'];
        $email = $data[0]['email'];
        $public_email = $data[0]['public_email'];
        $show_public_email = (bool) $data[0]['show_public_email'];
        $cell = $data[0]['phone'];
        $public_phone = $data[0]['home_phone'];

        $sector = $data[0]['sector'];
        $web = $data[0]['web'];
        $job = $data[0]['job'];
        $work = $data[0]['work'];

        if (isset($data[0]['work_on_vessel'])) {
            $work_on_vessel = (int) $data[0]['work_on_vessel'];
        }

        if (isset($data[0]['vessel'])) {
            $work = $data[0]['vessel'];
            $isVessel = TRUE;
        }

        if (isset($work) && !empty($work)) {
            $work_id = $this->getEmployerId($work, $isVessel);  // if work is empty, will be created a new one
        }

        if(isset($data[0]['skills']) && is_array($data[0]['skills'])) {
            $skills = $data[0]['skills'];
            $result = $this->setSkills($id, $skills);
            if (!$result)
                return json_encode(array('error' => 'update skills'));
        }
        //  update user data
        $query = "UPDATE users SET ";
        if(!empty($name))
            $query .= "name = '$name', ";
        if(!empty($pass))
            $query .= "password = '" . md5($pass) . "', ";
        if(!empty($email))
            $query .= "email = '$email', ";
        if(!empty($public_email))
            $query .= "public_email = '$public_email', ";
        if(!empty($show_public_email))
            $query .= "show_public_email = '$show_public_email', ";
        if(!empty($cell))
            $query .= "cell = '$cell', ";
        if(!empty($public_phone))
            $query .= "public_phone = '$public_phone', ";
        if(!empty($sector))
            $query .= "sector = '$sector', ";
        if(!empty($web))
            $query .= "www = '$web', ";
        if(!empty($job))
            $query .= "occupation = '".addslashes($job)."', ";
//                 if(!empty($work_on_vessel)   )
        if(isset($work_on_vessel)   )
            $query .= "work_on_vessel = $work_on_vessel, ";
        if(!empty($work))
            $query .= "company = '".addslashes($work)."' ";

        $query = rtrim($query, " ,");
        $query .= " WHERE uid = $id";

        // echo "\n", $query;
        // die;

        if($query != "UPDATE users SET WHERE uid = {$id}") {    // if updating only photo
            $result = mysql_query($query);
        //                 print_r($result); die;
            if (!$result)
            return json_encode(array('error' => 'update info'));
        }

        if (isset($photo)) {
            $result = $this->setUserPhoto($id, $photo);
            if (!$result)
                return json_encode(array('error' => 'update photo'));
        }

        //  Work update
        if(isset($data[0]['start_date']) && (isset($data[0]['stop_date'])||isset($data[0]['current_work'])) ) {

            $check_work = $this->getData("SELECT wid FROM work WHERE uid={$id} LIMIT 1");
            if(empty($check_work) || empty($check_work[0])){
                $default_work['uid'] = $id;
                $default_work['start'] = '';
                $default_work['stop'] = '';
                $default_work['descr'] = '';
                $default_work['www'] = '';
                $default_work['employer'] = 8400576;    //'New in the Business'
                $default_work['occupation'] = 8401657;  //'Seeking Employment'
                $q = "INSERT INTO work (" . implode(",", array_keys($default_work)) . ") VALUES ('" . implode("','", $default_work) . "')";
                if (!mysql_query($q))
                    return json_encode(array('error' => 'default work was not added'));
            }

            // Present work_id
            $employer_id = $this->getData("SELECT employer FROM work WHERE uid = {$id} ORDER BY wid DESC LIMIT 1");
            $employer_id = current($employer_id);
            $employer_id = $employer_id['employer'];

            $start = $data[0]['start_date'];
            $stop = $data[0]['stop_date'];
            $start_date = $this->dateConvert($start);
            $stop_date = $this->dateConvert($stop);
            $current_work = $data[0]['current_work'];
            if(isset($current_work) && $current_work)
                $stop_date = '0000-00-00 00:00:00';

            $query = "SELECT gid FROM pages WHERE cat = 106 AND gname = '{$job}' LIMIT 1";
            $job_id = $this->getData($query);
            $job_id = current($job_id);
            $job_id = $job_id['gid'];

            $feed_work = '';
            if(empty($job) && !empty($employer_id) && !empty($work_id)){
                $feed_work = 'employer';
                $q = "UPDATE `work` SET `start` = '{$start_date}', `stop` = '{$stop_date}', `employer` = {$work_id} WHERE `uid` = {$id} AND `employer` = {$employer_id} LIMIT 1";
            }
            elseif(empty($work) && !empty($job_id)){
                $feed_work = 'occupation';
                $q = "UPDATE `work` SET `start` = '{$start_date}', `stop` = '{$stop_date}', `occupation` = {$job_id} WHERE `uid` = {$id} AND `employer` = {$employer_id} LIMIT 1";
            }
            elseif(!empty($employer_id) && !empty($job_id)){
                $feed_work = 'all';
                $q = "UPDATE `work` SET `start` = '{$start_date}', `stop` = '{$stop_date}', `employer` = {$work_id}, `occupation` = {$job_id} WHERE `uid` = {$id} AND `employer` = {$employer_id} LIMIT 1";
            }
            else{
                $feed_work = 'date';
                $q = "UPDATE `work` SET `start` = '{$start_date}', `stop` = '{$stop_date}' WHERE `uid` = {$id} AND `employer` = {$employer_id} LIMIT 1";
            }

        // echo $q, "\n\n"; //die;
            if (!mysql_query($q))
                return json_encode(array('error' => 'update works'));

            switch($feed_work){
                case 'employer':
                    $this->feedAdd("G", $work_id, $id, $id, $work_id);
                    break;
                case 'occupation':
                    $this->feedAdd("G", $job_id, $id, $id, $job_id);
                    break;
                case 'all':
                    $this->feedAdd("G", $job_id, $id, $id, $job_id);
                    $this->feedAdd("G", $work_id, $id, $id, $work_id);
                    break;
            }
            $this->feedAdd("PR5", 0, $id, $id, 0);

            $result = true;
        }
        return json_encode(array("result" => $result));
    }

    public function updatePhoto($data, $file) {        //    $file for test here
        $data = json_decode($data, true);
        $id =  (int) $data[0]['id'];
        $url = $data[0]['url'];
//     print_r($file); die;
        if (isset($id) && !empty($id)) {
            if (isset($url)) {
                $file = file_get_contents($url);
                $tmp_name = "/tmp/" . uniqid();
                file_put_contents($tmp_name, $file);
                $photo['tmp_name'] = $tmp_name;
//                 unlink($tmp_name);

                $result = $this->setUserPhoto($id, $photo);
                $result = $this->getUserPhoto($id);
                if (!$result)
                    return json_encode(array('error' => 'update photo'));
            }
        }
        else
            return json_encode(array("error" => 'No id'));

        return json_encode(array("result" => $result));
    }

    private function getData($query){
        $array = mysql_query($query);
        $result = array();

        if ($array == false)
            $result[] = null;
        else{
            while ($row = mysql_fetch_assoc($array)){
                $result[] = $row;
            }
        }
        return $result;
    }

    /*
    [{"user1": "11805", "user2": "11793"}]
    */

    public function setFriends($data) {
        $data = json_decode($data, true);
        $user1 =  (int) $data[0]['user1'];
        $user2 =  (int) $data[0]['user2'];

        if ($user1 == $user2)
            $result = 'You can not connect to your account';
        else {
            if (!$this->isFriends($user1, $user2)) {
            // add friend
                $query = "INSERT INTO friends(id1, id2, status) VALUES(" . $user1 . ", " . $user2 . ", 1)";
                $result = mysql_query($query);
                if (!$result)
                    $result = false;
                else {
                    // send notification
                    $query = "INSERT INTO notifications (uid, type, from_uid) VALUES (" . $user1 . ", 'f', " . $user2 . ")";
                    mysql_query($query);
                    $query = "INSERT INTO notifications (uid, type, from_uid) VALUES (" . $user2 . ", 'f', " . $user1 . ")";
                    mysql_query($query);

                    $query = "SELECT fid FROM friends WHERE id1 = " . $user1 . " and id2 = " . $user2;
                    $result = $this->getData($query);
                    $fid = $result[0]['fid'];

                    if (!empty($fid)) {
                        $this->feedAdd("F", $fid, $user1, $user2);
//                         $this->addFeed("F", 1, $fid, $user1, $user2);
//                         $this->addFeed("F", 1, $fid, $user2, $user1);

                        $result = true;
//                         $this->sendNotificationToMail($user1, $user2);
                    }
                    else
                        $result = 'feed id is empty';
                }
            }
            else
                $result = 'already friends';
        }
        return json_encode(array("result" => $result));
    }

    public function deleteFriends($data) {
        $data = current(json_decode($data, true));
        $user1 =  (int) $data['user1'];
        $user2 =  (int) $data['user2'];

        if ($user1 == $user2) {
            $result = 'You can not delete yourself from friends';
        }
        elseif(isset($user1, $user2) && !empty($user1) && !empty($user2)) {
            $result = mysql_query("DELETE FROM `friends` WHERE ((`id1` = {$user1} AND `id2` = {$user2}) OR (`id2` = {$user1} AND `id1` = {$user2}))  LIMIT 1");
        }
        else
            $result = 'incorrect parameters';
        return json_encode(array("result" => $result));
    }

    private function getProfileURL($uid) {
        $query = "select username from users WHERE uid = $uid";
        $result = $this->getData($query);
        $username = $result[0]['username'];

        if (empty($username)) {
            return "/user/_$uid";
        } else {
            $username = str_replace(" ", ".", $username);
            return "/user/$username";
        }
    }

    private function sendMail($uid, $name) {
        include "../inc/phpmailer.php";

        $query = "select content from static where id='connect_accept'";
        $result = $this->getData($query);

        $subj = "Connect with " . $name;
        $from = "notifications";
        $email = $this->getEmail($uid);

        if (!empty($email)) {
            $msg = $result[0]['content'];
            $msg = str_replace("{NAME_FROM}", $name, $msg);
            $msg = str_replace("{PROFILE_URL}", "http://www.salthub.com" . $this->getProfileURL($uid), $msg);
            $msg = str_replace("{SITENAME}", 'salthub.com', $msg);
            $msg = nl2br($msg);

            $mail->SetFrom("$from@SaltHub.com", "SaltHub");
            $mail->isHTML(true);
            $mail->AddAddress($email);
            $mail->Subject = $subj;
            $mail->Body = template($msg, $email);
            $mail->Send();

            return true;
        }
        else
            return 'email is empty';
    }

    private function getEmail($uid) {
        $query = "select email from users where uid = " . $uid;
        $result = $this->getData($query);

        return $result[0]['email'];
    }

//     private function addFeed($type, $from_app, $id, $uid_to, $uid_from, $gid = 0, $last_update = null) {
//         $feed = array(
//             "type" => $type,
//             "link" => $id,
//             "uid" => $uid_to,
//             "uid_by" => $uid_from,
//             "gid" => $gid,
//             "last_update" => $last_update,
//             "from_app" => $from_app
//         );
//
//         mysql_query("insert into feed (" . implode(",", array_keys($feed)) . ") values ('" . implode("','", $feed) . "')");
//         $feed['fid'] = mysql_insert_id();
//         $result = $feed['fid'];
//
//         return $result;
//     }

    private function isFriends($user1, $user2) {
        $query = "SELECT COUNT(*) as 'count' FROM friends WHERE (id1 = " . $user1 . " AND id2 = " . $user2 . ") or (id2 = " . $user1 . " AND id1 = " . $user2 . ")";
        $count = $this->getData($query);

        return $count[0]['count'] > 0 ? true : false;
    }

    private function getName($user) {
        $query = "select name from users where uid = " . $user;
        $result = $this->getData($query);

        return $result[0]['name'];
    }

    public function sendNotificationToMail($user1, $user2) {
        $user1_name = $this->getName($user1);
        $user2_name = $this->getName($user2);

        $this->sendMail($user1, $user1_name);
        $this->sendMail($user2, $user2_name);
    }

    private function getUserPhoto($uid) {
        $query = "SELECT CONCAT(users.container_url, '/', photos.hash, '.jpg') AS url FROM photos INNER JOIN users ON users.pic = photos.id WHERE users.uid = " . $uid;
//         $url = $this->api_db_query($query);
        $url = quickQuery($query);
        return $url;

    //         $query = "SELECT photos.hash FROM photos INNER JOIN users ON users.pic = photos.id WHERE users.uid = " . $uid;
    //         $photo = $this->getData($query);
    //         if ($photo == null)
    //             return null;
    //         $query = "select container_url as 'hash' from users where uid = " . $uid;
    //         $cont = $this->getData($query);
    //         $url = $cont[0]['hash'] . '/' . $photo[0]['hash'] . '.jpg';
    //         return $url;
    }

    private function setUserPhoto($uid, $data) {
        $tmp = $data['tmp_name'];

        $hash = md5(microtime());

        $result = $this->getData("select id from albums where uid = " . $uid . " and title = 'profile photos'");
        if ($result == null)
            mysql_query("insert into albums (uid,title,descr,albtype) values (" . $uid . ",'Profile Photos','My profile photos',2)");

        $result = $this->getData("select id from albums where uid = " . $uid . " and albType=2");
        $aid = $result[0]['id'];

        $query = "insert into photos(uid,aid,hash,width,height,privacy) values (" . $uid . ", " . $aid . ", '$hash',178,266,0)";
        mysql_query($query);
        $id = mysql_insert_id();
        mysql_query("update albums set mainImage=" . $id . " where uid = " . $uid . " and albType=2");        //    to albums set mainImage


        $info = getimagesize($tmp);

//         include_once '../inc/misc.php';
        $jmp = shortenURL("http://dev.salthub.com/photo.php?id=$id");
//         mysql_query("update photos set jmp='$jmp' where id=$id");
        mysql_query("update photos set jmp='{$jmp}', width={$info[0]}, height={$info[1]} where id={$id}");

//         $info = getimagesize($tmp);

//         $query = "update photos set width={$info[0]}, height={$info[1]} where id={$id}";
//         mysql_query($query);

        switch ($info['mime']) {
            case "image/jpeg":
            $img = @imagecreatefromjpeg($tmp);
            break;

            case "image/png":
            $img = @imagecreatefrompng($tmp);
            break;

            case "image/gif":
            $img = @imagecreatefromgif($tmp);
            break;
        }

        //         $new_img = ImageCreateTrueColor(178, 266);
        $target_file = "/tmp/$hash.jpg";

        //         imagecopyresampled($new_img, $img, 0, 0, 0, 0, 178, 266, $info[0], $info[1]);
        //         imagejpeg($new_img, $target_file, 90);
        imagejpeg($img, $target_file, 90);



        $this->uploadToCloud($uid, $target_file, $hash . ".jpg");

        $temp_thumb = "/tmp/" . mysql_thread_id();
        include_once '../inc/createThumb.php';

        if (file_exists($temp_thumb))
            unlink($temp_thumb);

        $file = createThumb(119, 95, true, true, $id, $hash, $temp_thumb);
        $this->uploadToCloud($uid, $file, $hash . "_wide.jpg");

        if (file_exists($temp_thumb))
            unlink($temp_thumb);
        $file = createThumb(178, 266, false, true, $id, $hash, $temp_thumb);
        $this->uploadToCloud($uid, $file, $hash . "_tall.jpg");

        if (file_exists($temp_thumb))
            unlink($temp_thumb);
        $file = createThumb(48, 48, true, true, $id, $hash, $temp_thumb);
        $this->uploadToCloud($uid, $file, $hash . "_square.jpg");

        $result = mysql_query("update users set pic = " . $id . " where uid = " . $uid);    //albums_pic + mainImage

        return $result;
    }
/*
    private function setUserPhoto($uid, $data) {
        $tmp = $data['tmp_name'];

        $hash = md5(microtime());

        $result = $this->getData("select id from albums where uid = " . $uid . " and title = 'profile photos'");
        if ($result == null)
            mysql_query("insert into albums (uid,title,descr,albtype) values (" . $uid . ",'Profile Photos','My profile photos',2)");

        $result = $this->getData("select id from albums where uid = " . $uid . " and albType=2");
        $aid = $result[0]['id'];

        $query = "insert into photos(uid,aid,hash,width,height) values (" . $uid . ", " . $aid . ", '$hash',178,266)";
        mysql_query($query);
        $id = mysql_insert_id();
        mysql_query("update albums set mainImage=" . $id . " where uid = " . $uid . " and albType=2");


        include_once '../inc/misc.php';
        $jmp = shortenURL("http://dev.salthub.com/photo.php?id=$id");
        mysql_query("update photos set jmp='$jmp' where id=$id");

        $info = getimagesize($tmp);

        switch ($info['mime']) {
            case "image/jpeg":
                $img = @imagecreatefromjpeg($tmp);
                break;

            case "image/png":
                $img = @imagecreatefrompng($tmp);
                break;

            case "image/gif":
                $img = @imagecreatefromgif($tmp);
                break;
        }

        //$img = @imagecreatefrompng($tmp);
        $info = getimagesize($tmp);
        $new_img = ImageCreateTrueColor(178, 266);
        $target_file = "/tmp/$hash.jpg";

        imagecopyresampled($new_img, $img, 0, 0, 0, 0, 178, 266, $info[0], $info[1]);
        imagejpeg($new_img, $target_file, 90);



        $this->uploadToCloud($uid, $target_file, $hash . ".jpg");

        $temp_thumb = "/tmp/" . mysql_thread_id();
        include_once '../inc/createThumb.php';

        if (file_exists($temp_thumb))
            unlink($temp_thumb);

        $file = createThumb(119, 95, true, true, $id, $hash, $temp_thumb);
        $this->uploadToCloud($uid, $file, $hash . "_wide.jpg");

        if (file_exists($temp_thumb))
            unlink($temp_thumb);
//         $file = createThumb(178, 266, false, true, $id, $hash, $temp_thumb);
        $file = createThumb(266, 266, false, true, $id, $hash, $temp_thumb);
        $this->uploadToCloud($uid, $file, $hash . "_tall.jpg");

        if (file_exists($temp_thumb))
            unlink($temp_thumb);
        $file = createThumb(48, 48, true, true, $id, $hash, $temp_thumb);
        $this->uploadToCloud($uid, $file, $hash . "_square.jpg");

        $result = mysql_query("update users set pic = " . $id . " where uid = " . $uid);

        return $result;
    }
*/
    function uploadToCloud( $uid = null, $file, $dest_name ){
      include_once( $_SERVER['DOCUMENT_ROOT'] . "/inc/rackspace/cloudfiles.php" );
      if( $uid == null )
        die('no uid');

        $objRackspaceAuthentication = new CF_Authentication('tweider', '2eafbeef6d3c8e96728ebd33e031c533');
        $blAuthenticated = $objRackspaceAuthentication->authenticate();
        $objRackspaceConnection = new CF_Connection ($objRackspaceAuthentication);
        $objRackspaceConnection->setDebug (false);
        $objRackspaceConnection->ssl_use_cabundle();

        $this->CloudConnection = $objRackspaceConnection;


      $createContainer = false;
      $containerName = "usr_" . $uid;

      if( strlen( quickQuery("select container_url from users where uid='$uid'") ) > 0 )
      {
        try {
             $container = $objRackspaceConnection->get_container($containerName);
        }
        catch( Exception $e ){
          $createContainer = true;
        }
      }
      else
        $createContainer = true;

      if( $createContainer )
      {
        $container = $objRackspaceConnection->create_container($containerName);
        $url = addslashes( $container->make_public() );
        mysql_query( "update users set container_url='$url' where uid='" . $uid . "'" );
      }

      if( !file_exists( $file ) )
      {
        if( $this->admin )
          echo "Unable to find file: $file\n";
        return false;
      }

      $remote_obj = $container->create_object( $dest_name );

      if( !$remote_obj )
      {
        echo "Unable to create remote file: $file\n";
        return false;
      }

      if( !( $remote_obj->write ( fopen($file, "r"), filesize($file) ) ) )
      {
        echo "Unable to write file: $file\n";
        return false;
      }

      //var_dump($remote_obj);

      //echo "\n\ngood\n\n\n";

      return true;
    }
/*    private function uploadToCloud($uid = null, $file, $dest_name) {
        include_once( $_SERVER['DOCUMENT_ROOT'] . "/inc/rackspace/cloudfiles.php" );

        if ($uid == null)
            $uid = 11810;

        $objRackspaceAuthentication = new CF_Authentication('tweider', '2eafbeef6d3c8e96728ebd33e031c533');
        $blAuthenticated = $objRackspaceAuthentication->authenticate();
        $objRackspaceConnection = new CF_Connection($objRackspaceAuthentication);
        $objRackspaceConnection->setDebug(false);
        $objRackspaceConnection->ssl_use_cabundle();

        $this->CloudConnection = $objRackspaceConnection;

        $createContainer = false;
        $containerName = "usr_11810";

        if (strlen(quickQuery("select container_url from users where uid='$uid'")) > 0) {
            try {
            $container = $objRackspaceConnection->get_container($containerName);
            } catch (Exception $e) {
            $createContainer = true;
            }
        } else
            $createContainer = true;

        if ($createContainer) {
            $container = $objRackspaceConnection->create_container($containerName);
            $url = addslashes($container->make_public());
            mysql_query("update users set container_url='$url' where uid='" . $uid . "'");
        }

        if (!file_exists($file)) {
            return false;
        }

        $remote_obj = $container->create_object($dest_name);

        if (!$remote_obj) {
            return false;
        }

        if (!( $remote_obj->write(fopen($file, "r"), filesize($file)) )) {
            return false;
        }
        var_dump($remote_obj);
        //echo "\n\ngood\n\n\n";

        return true;
    }
*/
    /*
     * these methods are not used
     */

    private function getSkills($id) {
        $query = "SELECT users.contactfor as 'skills' FROM users WHERE users.uid = " .  (int) $id;
//         $query = "SELECT users.contactfor as 'skills' FROM users WHERE users.uid = 11999";
        $result = $this->getData($query);

//         if ($result == null OR $result[0] == null)
        if ($result == null OR empty($result[0]['skills']))
//             return null;
            return array();
        else {
            $result[0]['skills'] = html_entity_decode($result[0]['skills']);

            $result = implode('', $result[0]);
// if(empty($result)) return array();
            $result = rtrim($result, chr(2));
            $data = explode(chr(2), $result);
            return $data;
        }
    }

    private function getJobByUser_old($id) {
        $query = "SELECT pages.gname AS 'job' FROM pages LEFT JOIN `work` ON work.occupation = pages.gid WHERE work.uid = " . $id;
        return $this->getData($query);
    }

    private function getWorkByUser_old($id) {
        $query = "SELECT pages.gname AS 'work' FROM pages LEFT JOIN `work` ON work.employer = pages.gid WHERE work.uid = " . $id;
        return $this->getData($query);
    }

    private function getJobByUser($id) {
        $query = "SELECT users.occupation as 'job' FROM users WHERE users.uid = " . $id;
        return $this->getData($query);
    }

    private function getWorkByUser($id) {
        $query = "SELECT users.company as 'work' FROM users WHERE users.uid = " . $id;
        return $this->getData($query);
    }

        // test photo
    public function test($params) {
        $data = current(json_decode($params, 1));
        $uid = $data['id'];
        $start = microtime(true);
        $query = "SELECT users.uid, photos.hash  FROM photos INNER JOIN users ON users.pic = photos.id WHERE users.uid = " . $uid;
        $photo = $this->getData($query);
        return $photo;
        if ($photo == null)
            return null;

        $query = "SELECT container_url as 'hash' FROM users WHERE uid = " . $uid;
        $cont = $this->getData($query);

        $url = $cont[0]['hash'] . '/' . $photo[0]['hash'] . '.jpg';

        echo $url;
        $time = microtime(true) - $start;
        printf("\rСкрипт выполнялся %.4F сек.", $time);
        $start = microtime(true);

        $query = "SELECT CONCAT(users.container_url, '/', photos.hash, '.jpg') AS url FROM photos INNER JOIN users ON users.pic = photos.id WHERE users.uid = " . $uid;
//         $photo = $this->getData($query);
//         $photo = current(current($photo));
        $photo = $this->api_db_query($query);
        echo $photo;
        $time = microtime(true) - $start;
        printf("\rСкрипт выполнялся %.4F сек.", $time);
        die();
        return $url;

        return json_encode(array('user' => $result));
    }

    /*
    * *Added by Me
    */

    public function myTest($data, $photo) {
        $data = json_decode($data, true);
        $login = $data[0]['login'];
        $pass = $data[0]['pass'];
        $uid = $data[0]['uid'];
$x = mysql_query("select subj,body,u.uid,u.username,u.name,u.pic,v.uid as suid,v.username as susername,v.name as sname,-1 as status from friend_suggestions f inner join users u on u.uid=if(id1=$uid,id2,id1) inner join users v on v.uid=f.uid where (id1=$uid or id2=$uid) order by u.name");
  while ($y = mysql_fetch_array($x, MYSQL_ASSOC))
  {
      $suggest_uids[] = $y['uid'];
        $suggests[] = $y;
  }
print_r($suggest_uids);
print_r($suggests);
die;
//         return json_encode(array('user' => $result));
    }

    //sends private message to a user, (copied from API class)
//     function sendMessage($uid, $subj, $body, $from = null, $notify = true)
//  [{"uid"}:{""},{"uid_to"}:{""},{"subj"}:{""},{""}:{""}]
    function sendMessage($data) {
        $data = json_decode($data, true);
        $uid =  (int) $data[0]['uid'];
        $uid_to =  (int) $data[0]['uid_to'];
        $subj = $data[0]['subj'];
        $body = $data[0]['body'];

//                 if( empty($from) ) $from = $this->uid;
//                 $real_uid = $this->uid;
//                 $this->uid = $from;

        $query = "insert into messages (unreadByRecv,uid,uid_to,subj) values (" . implode(",", array(1, $uid, $uid_to, "'" . $subj . "'")) . ")";
        mysql_query($query);
        $mid = mysql_insert_id();
        $query = "insert into comments (type,link,comment,uid) values (" . implode(",", array("'M'", $mid, "'" . addslashes($body) . "'", $uid)) . ")";
        mysql_query($query);

//                 if( $notify )
//                     $this->sendNotification(NOTIFY_MESSAGE, array("uid" => $uid, "mid" => $mid));
//
//                 $this->uid = $real_uid;
//                 return $mid;
        return json_encode(array("mid" => "$mid"));
    }

    function updatePass($data) {            // not used
        $data = json_decode($data, true);
        $email = $data[0]['email'];
        $pass = $data[0]['pass'];
        $pass = md5($pass);
        $q = "UPDATE users SET password = '$pass' WHERE email = '$email' LIMIT 1";
        mysql_query($q);
    }


    function dateConvert($date){              //  Y-m-d <-> m-d-Y
        switch(strpos($date, '-')){
            case 4:
                $res = substr($date,5,5) .'-'. substr($date,0,4);
                break;
            case 2:
                $res = substr($date,6,4) .'-'. substr($date,0,5);
                break;
            default:
                $res = '';
        }
        return $res;
    }

    /**
    * @param typeOfFedd $type  //C for comment, P for photo, etc.
    * @param $id               //id of the item defined by "type"
    * @param null $uid_to      //the uid for user whose feed is being posted to
    * @param null $uid_from    //the uid of the user posting on the feed
    * @param int $gid          //gid if action took place on a page
    * @return int
    */

    private function feedAdd($type, $id, $uid_to = null, $uid_from = null, $gid = 0, $last_update = null, $from_app = 1){

        $feed = array(
            "type"      => $type,
            "link"      => $id,
            "uid"       => $uid_to,
            "uid_by"    => $uid_from,
            "gid"       => $gid,
            "last_update" => $last_update,
            "from_app" => $from_app
        );

        //To prevent duplicate feed entries
//         if($type != VP4){
//             if( $uid_to == -1 && $uid_from == -1 ){
//                 $count = quickQuery( "select Count(*) from feed where type='$type' and uid=0 and uid_by=0 and link='$id' and gid='$gid' and ts > DATE(NOW())" );
//             }
//             else{
                $count = quickQuery( "select Count(*) from feed where type='$type' and uid='$uid_to' and uid_by='$uid_from' and link='$id' and gid='$gid' and ts > DATE(NOW())" );
//             }

        if( $count > 0 ){
                return;
                //echo $count;
        }
//         }
//         elseif($type == VP4){
//             $feed_vp4 = quickQuery( 'select `type` from feed where type in("VP1", "VP2", "VP3", "VP4") and uid=0 and uid_by=0 and gid="'.$gid.'" order by ts desc' );
//             if($feed_vp4 == VP4){
//                 return;
//             }
//         }

        sql_query("insert into feed (" . implode(",", array_keys($feed)) . ") values ('" . implode("','", $feed) . "')");
//             $feed['fid'] = mysql_insert_id();
//             $result = $feed['fid'];
//
//             return $result;
    }

    private function getFriendsIdsArr($uid, $status=0) {        //есть такая же publiс, переделаьть
//         $query = "SELECT uid FROM friends INNER JOIN users ON IF(id1 = {$uid}, id2, id1) = users.uid  WHERE (id1 = {$uid} OR id2 = {$uid}) AND active = 1";
        $query = "SELECT IF(id1=$uid,id2,id1) AS uid FROM friends WHERE (id1=$uid OR id2=$uid)";
        if($status)
            $query .= ' AND status = 1';
        $result = $this->getData($query);

        $friends = array();
        if ($result != null) {
            foreach ($result as $key => $value) {
                $friends[] = $value['uid'];
            }
        }

        return $friends;
    }

    private function getFriendsOfFriends( $uid ){
//         $data = current(json_decode($uid, true));    For Test
//         $uid = $data['id'];

        $friends = array();
        $x = sql_query( "select if(id1=  {$uid}  ,id2,id1) as uid from friends where (  {$uid} ) in (id1,id2) and status=1" );

        while ($friend = mysql_fetch_array($x, MYSQL_ASSOC)){
            if( $friend['uid'] != $uid )
            $friends[] = $friend['uid'];
        }

        foreach( $friends as $friend ){
            $friends_of_friend = sql_query( "select if(id1=" . $friend . ",id2,id1) as uid from friends where (" . $friend . ") in (id1,id2) and status=1" );

            while ($result = mysql_fetch_array($friends_of_friend, MYSQL_ASSOC)){
                if( $result['uid'] != $uid && !in_array($result['uid'], $friends ) )
                    $friends[] = $result['uid'];
            }
        }

        return $friends;
    }

    public function acceptConnectRequest($data) {
        $data = current(json_decode($data, true));
        $user1 = (int) $data['id1'];
        $user2 = (int) $data['id2'];

        if ($user1 == $user2)
            $result = 'You can`t accept connection with yourself';
        else {
            $query = "UPDATE friends SET status = 1 WHERE id1 = {$user2} AND id2 = {$user1} AND status = 0 LIMIT 1";
            $result =     mysql_query($query);
            if (!$result)
                $result = false;
            else {
                // send notification
                $query = "INSERT INTO notifications (uid, type, from_uid) VALUES (" . $user1 . ", 'f', " . $user2 . ")";
                mysql_query($query);
                $query = "INSERT INTO notifications (uid, type, from_uid) VALUES (" . $user2 . ", 'f', " . $user1 . ")";
                mysql_query($query);

//                 $query = "SELECT fid FROM friends WHERE id1 = " . $user1 . " and id2 = " . $user2;
//                 $result = $this->getData($query);
                $fid = mysql_insert_id();

                if (!empty($fid)) {
                    $this->feedAdd("F", $fid, $user1, $user2);
                    $result = true;
                }
                else
                    $result = 'feed id is empty';
            }

        }
        return json_encode(array("result" => $result));

    }

//             Array(
//                 [work_id] => 2603
//                 [work_start] => 01-01-2000
//                 [work_stop] => 00-00-0000
//                 [current_work] => 1
//             )
    private function getWorkInfoByUser($uid) {
        $query = " SELECT wid as work_id, start as work_start, stop as work_stop FROM work WHERE uid = {$uid} ORDER BY wid DESC LIMIT 1 ";
        $work = current($this->getData($query));

        $work['work_start'] = $this->dateConvert($work['work_start']);
        $work['work_stop'] = $this->dateConvert($work['work_stop']);
        $work['current_work'] = ($work['work_stop'] == '00-00-0000') ? true : false ;

        return $work;
    }

    public function peopleMayKnow($data){
        $page_limit = 9;

        $data = current(json_decode($data, true));
        $uid = (int) $data['id'];
        $page_num = isset($data['page_num']) ? $data['page_num'] : 0;

        $page_offset = $page_num * $page_limit;
        $custom_limit .= $page_offset .', '. $page_limit;

        $my_sector = quickQuery( "select sector from users where uid='{$uid}'" );
        $fof = array();
        $fof = $this->getFriendsOfFriends($uid);
        if(count($fof) == 0)
            $fof[] = 0;

        $fof_implode =  implode(",", $fof);

        $my_works = "SELECT gid FROM pages INNER JOIN work ON work.occupation = pages.gid WHERE work.uid = {$uid}";
        $my_works = $this->getData($my_works);
//         print_r($my_works);
        $my_works_ar = array();
        foreach($my_works as $val){
            $my_works_ar[] = $val['gid'];
        }
        if(count($my_works_ar) == 0)
            $my_works_ar[] = 0;
        $my_works_implode = implode(",", $my_works_ar);

        $blocked_uids = array();
        $q = mysql_query( "select blocked_uid from blocked_users where uid='{$uid}'" );
        while( $r = mysql_fetch_array( $q ) )
            $blocked_uids[] = $r['blocked_uid'];
        $blocked_uids[] = 832; //Make sure that "SaltHub" is not added to the list.

        $friends = $this->getFriendsIdsArr($uid);
// print_r($friends); die;

//         $ar = array();
//         foreach ($friends as $fr){
//             $ar[] = $fr['uid'];
//         }
//         $ar = array_merge($ar, $sugg_ar);

        if (count($ar) == 0){
            $ar[] = '0';
        }
        if (count($friends) == 0){
            $friends[] = '0';
        }


        $sector = "";
        if( $my_sector > 0){
            $sector .= " OR sector='$my_sector'";
        }

        $query = "SELECT DISTINCT
            users.uid,
            users.uid IN($fof_implode) AS fof,
            pages.gid IN($my_works_implode) AS empl,
            sector='$my_sector' AS sector
            FROM users
            LEFT JOIN `work` ON `work`.uid = users.uid
            LEFT JOIN pages ON pages.gid = work.occupation
            WHERE users.active=1
            AND users.verify=1
            AND users.dummy=0
            AND users.uid != {$uid}
            AND users.uid NOT IN(".implode(',', $blocked_uids).")
            AND users.uid NOT IN(".implode(',', $friends).")
            AND(users.uid IN($fof_implode)
            OR pages.gid IN($my_works_implode)
            $sector)";

        $query .= " ORDER BY
            fof DESC,
            empl DESC,
            sector DESC,
            users.uid
            LIMIT {$custom_limit}";
//         echo $query;    die;


        $x = mysql_query($query);
        while ($result = mysql_fetch_array($x, MYSQL_ASSOC)){
            //if( $result['uid'] != $API->uid )
            $results[] = $result['uid'];
        }
//         print_r($results);
        @shuffle($results);

    //If we still don't have enough people, just pick anyone with a photo.
        if( sizeof( $results ) < $page_limit ){
            $limit = $page_limit - sizeof( $results );
            $query = "SELECT uid FROM users WHERE active=1 AND uid != {$uid} AND pic>0 AND active=1 ORDER BY RAND() LIMIT {$limit}";
            $x = sql_query($query);
            while ($result = mysql_fetch_array($x, MYSQL_ASSOC)){
                $results[] = $result['uid'];
            }
        }


        $result = array();

        foreach($results as $key => $val){
            $result[$key] = $this->getUserInfo($val);

            $query = " SELECT wid as work_id, start as work_start, stop as work_stop FROM work WHERE uid = {$val} ORDER BY wid DESC LIMIT 1 ";
            $work = $this->getData($query);

            $result[$key]['work_start'] = $this->dateConvert($work[0]['work_start']);
            $result[$key]['work_stop'] = $this->dateConvert($work[0]['work_stop']);
            $result[$key]['current_work'] = ($work[0]['work_stop'] == '00-00-0000') ? true : false ;
        }

        return json_encode(array('users' => $result));
    }

    public function SuggestionsAndRequests($data) {
        $data = current(json_decode($data, true));
        $uid = (int) $data['id'];

        $query = "SELECT id2 FROM friends INNER JOIN users ON users.uid = friends.id2  WHERE id1 = {$uid} AND active = 1 AND STATUS = 0";
        $from_you = $this->getData($query);

        $query = "SELECT id1 FROM friends INNER JOIN users ON users.uid = friends.id1  WHERE id2 = {$uid} AND active = 1 AND STATUS = 0";
        $to_you = $this->getData($query);

        $result = array();
        if($from_you){
            foreach($from_you as $key => $val){
                $result['from_you'][$key] = $this->getUserInfo($val['id2']);
                foreach($this->getWorkInfoByUser($val['id2']) as $k => $v)
                    $result['from_you'][$key][$k] = $v;
//                 $result['from_you'][$key]['mutual'] = $this->getFriendsOfFriends($val['id2']);
            }
        }
        else
            $result['from_you'] = array();

        if($to_you){
            foreach($to_you as $key => $val){
                $result['to_you'][$key] = $this->getUserInfo($val['id1']);
                foreach($this->getWorkInfoByUser($val['id1']) as $k => $v)
                    $result['to_you'][$key][$k] = $v;
//                 $result['to_you'][$key]['mutual'] = $this->getFriendsOfFriends($val['id1']);
            }
        }
        else
            $result['to_you'] = array();

        $friends = array();
        $friends = $this->getFriendsIdsArr($uid, 1);
        foreach($friends as $key => $val) {
            $id = $val;
            $friends[$key] = array('id' => $id );
            $friends[$key] = $this->getUserInfo($id);
            foreach($this->getWorkInfoByUser($id) as $k => $v)
                $friends[$key][$k] = $v;
//             $friends[$key]['mutual'] = $this->getFriendsOfFriends($id);
        }
//         print_r($friends);    die;


        $result['friends'] =  $friends;

        return json_encode($result);

    }

    public function SuggestionsAndRequests2($data) {
        $data = current(json_decode($data, true));
        $uid = (int) $data['id'];


//Load blocked uids
$blocked_uids = array();
$q = mysql_query( "select blocked_uid from blocked_users where uid= {$uid}" );
while( $r = mysql_fetch_array( $q ) )
  $blocked_uids[] = $r['blocked_uid'];

if( empty( $limit ) )
{
  if( isset( $_GET['limit'] ) )
    $limit = intval( $_GET['limit'] );
  else
    $limit = 3;
}

$uids = array();
$friends = array();

$q = sql_query( "select if(id1={$uid},id2,id1) as uid,status from friends where ({$uid}) in (id1,id2)" );
while( $r = mysql_fetch_array( $q ) )
  if( $r['status'] == 1 )
    $friends[] = $r['uid'];
  else
    $uids[] = $r['uid'];

$q = sql_query( "select id2 from friend_suggestions where id1='{$uid}'" );
while( $r = mysql_fetch_array( $q ) )
  if( !in_array( $r['id2'], $friends ) )
    $uids[] = $r['id2'];


$q = sql_query( "select eid from contacts where site='2' and uid='{$uid}' and eid" );
while( $r = mysql_fetch_array( $q ) )
  if( !in_array( $r['eid'], $friends ) )
    $uids[] = $r['eid'];

$q = sql_query( "select users.uid from users inner join contacts on users.email=contacts.eid where contacts.uid='{$uid}' and contacts.site > 2" );
while( $r = mysql_fetch_array( $q ) )
  if( isset( $r['uid'] ) && !in_array( $r['uid'], $friends ) )
    $uids[] = $r['uid'];

//Look for people with the same email domain
//if( sizeof( $uids ) < 10 )
// {
//
//
//   $email = quickQuery( "select email from users where uid='{$uid}'" );
//
//   if( $email != "" )
//   {
//     $data = explode( "@", $email );
//
//
//
//     if( sizeof( $data ) > 1 )
//     {
//       $domain = strtolower( trim( $data[1] ) );
//
//       $restricted_domains = array( "gmail.com", "yahoo.com", "aol.com", "hotmail.com", "msn.com" );
//
//       if( !in_array( $domain, $restricted_domains ) )
//       {
//         $q = sql_query( "select users.uid from users where users.uid!='{$uid}' and users.email like '%$domain%'" );
//         while( $r = mysql_fetch_array( $q ) )
//           if( isset( $r['uid'] ) && !in_array( $r['uid'], $friends ) )
//             $uids[] = $r['uid'];
//       }
//     }
//   }
// }

$friends = $this->getFriendsIdsArr($uid, 1);


$uids = array_diff( $uids, $blocked_uids );
$uids = array_diff( $uids, $friends );

$uids = array_unique( $uids );

// print_r($uids);    die;
// $count = 0;
// if (isset($_GET['count']))
//     $count = $_GET['count']*9;
// $uids = array_slice($uids, $count);

// $cond = $this->getConditions( $uids );
$cond = 'IN (';
foreach( $uids as $key => $value ) {
    if( $value != "" ) {
        $cond .=  $value . ',';
    }
}
$cond = rtrim( $cond,  ',') . ') ';
//         $sql = "select uid,username,pic,name from users where active=1 and dummy=0 and uid != {$uid} and $cond limit 9"; //order by rand() limit $limit";
//         $sql = "select uid from users where active=1 and dummy=0 and uid != {$uid} and $cond limit 9"; //order by rand() limit $limit";
        $sql = "select uid from users where active=1 and dummy=0 and uid != {$uid} and uid $cond limit 9"; //order by rand() limit $limit";
        $uids = $this->getData($sql);
        $result = array();
//     print_r($uids);    die;

        foreach($uids as $key => $val){
            $result[$val['uid']] = $this->getUserInfo($val['uid']);
//             foreach($this->getWorkInfoByUser($val['uid']) as $k => $v)
//                 $result[$key][$k] = $v;
        }

$cond = ' IN (';
foreach( $uids as $key => $value ) {
    $cond .=  $value['uid'] . ',';
}
$cond = rtrim( $cond,  ',') . ') ';


        $sql = "SELECT * FROM friends
                WHERE
                ((id1 {$cond} AND id2='{$uid}')
                OR
                (id2 {$cond} AND id1='{$uid}'))
                AND
                STATUS = 0";
        $friend_suggestions = $this->getData($sql);

    foreach($friend_suggestions as $k => $v) {
        if($v['id1'] == $uid)
            $result[$v['id2']]['connection'] = 'from_you';
        else
            $result[$v['id1']]['connection'] = 'to_you';
    }
    foreach($result as $key =>  $val) {
        if(!isset($val['connection']))
            $result[$key]['connection'] = 'not';
    }
    $result = array_slice($result, 0);

        return json_encode(array('suggests' => $result));

    }
//     private function getConditions( $uids ) {        //    deprecated
//         $i = 0;
//
//         $cond = "(";
//         foreach( $uids as $key => $value )
//         if( $value != "" )
//         {
//         if( $i > 0 )
//             $cond .= " OR ";
//         $cond .= "uid=" . $value;
//         $i++;
//         }
//         $cond .= ")";
//         return $cond;
//     }

    public function requestToFriends($data) {
        $data = json_decode($data, true);
        $user1 =  (int) $data[0]['user1'];
        $user2 =  (int) $data[0]['user2'];

        if ($user1 == $user2)
            $result = 'You can not connect to your account';
        else {
            if (!$this->isFriends($user1, $user2)) {
            // add friend
                $query = "INSERT INTO friends(id1, id2, status) VALUES(" . $user1 . ", " . $user2 . ", 0)";
                $result = mysql_query($query);
                if (!$result)
                    $result = false;
                else
                    $result = true;
            }
            else
                $result = 'already friends';
        }
        return json_encode(array("result" => $result));
    }

    private function api_db_query($query){
        $res = mysql_query($query);
        if(!$res){
            echo mysql_error(), "\r\n";
            debug_print_backtrace();
            die();
        }
        else{
            $data = array();
            if(mysql_num_rows($res) > 1)
                    while($row = mysql_fetch_row($res, MYSQL_ASSOC)){
                        $data[current($row)] = $row;
                    }
            elseif(mysql_num_rows($res) == 1){
                    $data = mysql_fetch_row($res, MYSQL_ASSOC);
                    $data = current($data);    //    1 param only
            }
            mysql_free_result($res);
            return $data;
        }
    }


    /**
    *     <<facebook
    *     fb:app_id [content] => 115146131880453
    *     [fbAPIKey] => fa01c78aab6462c1750fcbdcc0933d80
    *     [fbSecret] => d5fa60115241b96bf865ddc902d48052
    *     $API->saveUserPicture(SITE_FACEBOOK, "http://graph.facebook.com/{$API->fbid}/picture?type=large");
    *
    *
    */


    public function fbLoginUser($data){

        $data = current(json_decode($data, true));

        $email = $data['email'];
        $pass = $data['pass'];
        $fbid = $data['fbid'];
        $name = $data['name'];
        $gender = $data['gender'];
        $dob = $data['dob'];
        $token = $data['token'];
        $expire = $data['expire'];
        $url = $data['url'];

//         if(!isset($email))
        if(!isset($fbid))
            return json_encode(0);
//         $query = "SELECT uid FROM users WHERE email = '{$email}' ";
        $query = "SELECT uid FROM users WHERE fbid = '{$fbid}'";



        $uid = quickQuery($query);
//         print_r($uid); die;
        if ($uid == null){                        // add user

            $username = $name;
            $new_username = preg_replace("/[^A-Za-z. ]/", '', $name);
            $i = '';
            do{
                $username = str_replace(' ', '.', $new_username.$i);
                $i++;
            }
            while (intval(quickQuery("select count(*) from users where replace(username, ' ', '.')='$username'")) > 0);

            $time = date('Y-m-d H:i:s', time());
            $user = array(
                'username' => $username,
                'email' => $email,
                'joined' => $time,
                'ip' => ip2long($_SERVER['REMOTE_ADDR']),
                'lastlogin' => $time,
                'fbid' => $fbid,
                'fbaccesstoken' => $token,
                'fbexpires' => $expire,
                'name' => addslashes($name),
                'fbusername' => addslashes($name),
                'active' => 0,
                'verify' => 1,
                'gender' => ucfirst( substr( $gender, 0, 1 ) ),
                'dob' => $dob
            );
//         }
//         else {
//             добавить апдейт имени! !
//             $time = date('Y-m-d H:i:s', time());
//             $user = array(
//                 'email' => $email,
//                 'joined' => $time,
//                 'ip' => ip2long($_SERVER['REMOTE_ADDR']),
//                 'lastlogin' => $time,
//                 'fbid' => $fbid,
//                 'name' => addslashes($name),
//                 'fbusername' => addslashes($name),
//                 'active' => 0
//             );
//         }

                $query = "insert into users (" . implode(",", array_keys($user)) . ") values ('" . implode("','", $user) . "')";

                $result = mysql_query($query);
                $id = mysql_insert_id();

    //             sql_query("update users set pic=" . intval(quickQuery("select id from photos where uid={$API->uid}")) . " where uid={$API->uid}");


                if($id){
                        if($url){
                            $file = file_get_contents($url);
                            $tmp_name = "/tmp/" . uniqid();
                            file_put_contents($tmp_name, $file);
                            $photo['tmp_name'] = $tmp_name;
            //                 unlink($tmp_name);

                            $result = $this->setUserPhoto($id, $photo);
//                             $result = $this->getUserPhoto($id);
                        }
                        $this->feedAdd("J", SITE_FACEBOOK, $id, $id);

                        $return = $this->getUserInfo2($id);
                        return json_encode(array('user' => $return));
                }
                else
                    return json_encode(array('error' => 'insert to users error'));
        }
        else{            //User exists
                $return = $this->getUserInfo2($uid);
                return json_encode(array('user' => $return));
        }

    }

//     public function spamWall($uid=14395, $fbid=100008106373842, $username='Artyom.Mikhaylenko') {
    public function spamWall($uid=14395, $fbid=100008106373842) {
/*
 *Constants for using in ios_api.
 */
define( "FB_APP_ID", 115146131880453 );
define( "FB_API_KEY", 'fa01c78aab6462c1750fcbdcc0933d80' );
define( "FB_SECRET", 'd5fa60115241b96bf865ddc902d48052' );
$siteName = 'SaltHub.com'; die;
// die($_SERVER['HTTP_HOST']);
$token = file_get_contents('https://graph.facebook.com/oauth/access_token?client_id=' .FB_APP_ID. '&client_secret=' .FB_SECRET. '&grant_type=client_credentials');
$token = ltrim(strstr($token, '='), '=');
// echo $token; die;
// echo $uid; die;


// if (empty($username))     //user has no username
//     $profile_url = "/user/_$uid";
// else                     //use the username
//     $profile_url = "/user/$username";



$graph_url = "https://graph.facebook.com/{$fbid}/feed";

$message = "I'm using $siteName. A professional network for individuals and businesses that maintain an identity in the maritime industries. www."  . $_SERVER['HTTP_HOST'] . "/?ref=" . $uid;
$caption = "$siteName allows you to grow your B2B, B2C and professional relationships in the Maritime industries. Be discovered and more!";
$name = "About $siteName";
// $link = "http://" . $_SERVER['HTTP_HOST'] . $profile_url;
$link = 'www.'  . $_SERVER['HTTP_HOST'] . "/?ref=$uid";
$picture = "http://" . $_SERVER['HTTP_HOST'] . "/images/salt_badge100.png";


// $attachment['media'][0]['type'] = "image";
//
// $attachment['media'][0]['src'] = "http://" . $_SERVER['HTTP_HOST'] . "/images/sitelogo.png";
// $attachment['name'] = "About $siteName.com";
//
// $attachment['caption'] = "$siteName allows you to grow your B2B, B2C and professional relationships in the Maritime industries. Be discovered and more!";
// $message = "I'm using $siteName. A professional network for individuals and businesses that maintain an identity in the maritime industries. "  . $_SERVER['HTTP_HOST'] . "/?ref=" . $uid;
//
// $attachment['media'][0]['type'] = "image";
// $attachment['media'][0]['src'] = "http://" . $_SERVER['HTTP_HOST'] . "/images/salt_badge100.png";
// $attachment['media'][0]['href'] = "http://" . $_SERVER['HTTP_HOST'] . "/?ref=" . $uid;
// $attachment['href'] = "http://" . $_SERVER['HTTP_HOST'] . "/?ref=" . $uid;


//   $postData = array(
//     'access_token' => $token,
//     'message' => $message,
//     'caption' => $caption,
//     'name' => $name,
//     'picture' => $picture,
//     'picture' => $picture,
//     'link' => $link);
//     'actions' => json_encode(array('name'=>'picture', 'link'=>$link)) );

  $postData = array(
    'access_token' => $token,
    'message' => $message,
    'caption' => $caption,
    'name' => $name,
    'picture' => $picture,
    'link' => "www.salthub.com/?ref=14395");
//     'actions' => json_encode(array('name'=>'picture', 'link'=>$link)) );

  $ch = curl_init();
  curl_setopt_array($ch, array(
  CURLOPT_URL => $graph_url,
  CURLOPT_POSTFIELDS => $postData,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_SSL_VERIFYPEER => false,
  CURLOPT_VERBOSE => true
  ));
  $result = json_decode(curl_exec($ch));
  curl_close($ch);
print_r($result);
                die;

$query = "select username from users where uid=$uid";
$username = quickQuery($query);

if (empty($username))     //user has no username
    $profile_url = "/user/_$uid";
else {                //use the username
    $username = str_replace(" ", ".", $username);
    $profile_url = "/user/$username";
}

$action_links[0]['text'] = "Find me on $siteName";
$action_links[0]['href'] = "http://" . $_SERVER['HTTP_HOST'] . $profile_url;

$attachment['media'][0]['type'] = "image";

$attachment['media'][0]['src'] = "http://" . $_SERVER['HTTP_HOST'] . "/images/sitelogo.png";
$attachment['name'] = "About $siteName.com";

$attachment['caption'] = "$siteName allows you to grow your B2B, B2C and professional relationships in the Maritime industries. Be discovered and more!";
$message = "I'm using $siteName. A professional network for individuals and businesses that maintain an identity in the maritime industries. "  . $_SERVER['HTTP_HOST'] . "/?ref=" . $uid;

$attachment['media'][0]['type'] = "image";
$attachment['media'][0]['src'] = "http://" . $_SERVER['HTTP_HOST'] . "/images/salt_badge100.png";
$attachment['media'][0]['href'] = "http://" . $_SERVER['HTTP_HOST'] . "/?ref=" . $uid;
$attachment['href'] = "http://" . $_SERVER['HTTP_HOST'] . "/?ref=" . $uid;

// $facebook->api(
//     array(
//         'method' => 'facebook.stream.publish',
//         'message' => $message,
//         'attachment' => json_encode($attachment),
//         'action_links' => json_encode($action_links)
//         )
//     );


$postdata = http_build_query(
    array(
        'access_token' => $token,
        'message' => $message,
    'attachment' => json_encode($attachment),
    'action_links' => json_encode($action_links)
    )
);
print_r($postdata); die;
$opts = array('http' =>
    array(
        'method'  => 'POST',
        'header'  => 'Content-type: application/x-www-form-urlencoded',
        'content' => $postdata
    )
);

$context = stream_context_create($opts);

// $result = file_get_contents('http://example.com/submit.php', false, $context);
$result = file_get_contents("https://graph.facebook.com/100008106373842/feed", false, $context);

print_r($result);
die;

// include_once $_SERVER["DOCUMENT_ROOT"] . "/fbconnect/base_facebook.php";
include_once $_SERVER["DOCUMENT_ROOT"] . "/fbconnect/facebook.php";
$siteName = 'SaltHub';
$facebook = new Facebook;

$query = "select username from users where uid=$uid";
$username = quickQuery($query);

if (empty($username)){ //user has no username
$profile_url = "/user/_$uid";
}
else //use the username
{
$username = str_replace(" ", ".", $username);
}
$profile_url = "/user/$username";

//         global $facebook, $API, $siteName, $site;
        //spam the user's wall letting the world know he joined mediabirdy

        $action_links[0]['text'] = "Find me on $siteName";
        $action_links[0]['href'] = "http://" . $_SERVER['HTTP_HOST'] . $profile_url;

        $attachment['media'][0]['type'] = "image";
//         if ($site == "m")
//         {
//             $attachment['media'][0]['src'] = "http://" . $_SERVER['HTTP_HOST'] . "/images/birdy2.png";
//             $attachment['name'] = "I just joined $siteName!";
//             $attachment['caption'] = "$siteName does one simple thing:  it allows you to share media on Facebook and Twitter!";
//             $message = "Look at this! It lets us share our videos and photos on Facebook and Twitter with one upload, in real time!";
//         }
//         else
//         {
            $attachment['media'][0]['src'] = "http://" . $_SERVER['HTTP_HOST'] . "/images/sitelogo.png";
            $attachment['name'] = "About $siteName.com";

            $attachment['caption'] = "$siteName allows you to grow your B2B, B2C and professional relationships in the Maritime industries. Be discovered and more!";
    //        $attachment['caption'] = "$siteName connects people who live, work and engage in and around the water.  It's used by enthusiasts, businessses and professsionals around the globe.  Give it a try.";
            $message = "I'm using $siteName. A professional network for individuals and businesses that maintain an identity in the maritime industries. "  . $_SERVER['HTTP_HOST'] . "/?ref=" . $uid;
//         }

        $attachment['media'][0]['type'] = "image";
        $attachment['media'][0]['src'] = "http://" . $_SERVER['HTTP_HOST'] . "/images/salt_badge100.png";
        $attachment['media'][0]['href'] = "http://" . $_SERVER['HTTP_HOST'] . "/?ref=" . $uid;
        $attachment['href'] = "http://" . $_SERVER['HTTP_HOST'] . "/?ref=" . $uid;

        $facebook->api(
            array(
                'method' => 'facebook.stream.publish',
                'message' => $message,
                'attachment' => json_encode($attachment),
                'action_links' => json_encode($action_links)
                )
            );

    }

    /*
    *     facebook >>
    **/

}
?>
