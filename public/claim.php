<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
$torrentId = $uid = 0;
$actionTh = $actionTd = '';

$seedTimeRequiredHours = \App\Models\Claim::getConfigStandardSeedTimeHours();
$uploadedRequiredTimes = \App\Models\Claim::getConfigStandardUploadedTimes();

$seedtime_order = ''; // 增加按本月做种时长排序
$unreached = ''; // 本月未达标
$unseeding = ''; // 所有已认领未做种

/**
 * @param $query
 * @param $seedTimeRequiredHours
 * @param $uploadedRequiredTimes
 * @return array
 */
function calculate_claim($query, $seedTimeRequiredHours, $uploadedRequiredTimes): array
{
    $claimResult = [];
    $claimResult['claim_count'] = (clone $query)->count();
    $claim_total_size = $reached_total_size = 0;
    $claim_total_list = (clone $query)
        ->with(['user', 'torrent', 'snatch'])
        ->get();
    foreach ($claim_total_list as $row) {
        $claim_total_size = bcadd($claim_total_size, $row->torrent->size);
    }
    $claimResult['claim_size'] = $claim_total_size;
    $reached_list = (clone $query)
        ->with(['user', 'torrent', 'snatch'])
        ->select("claims.*")
        ->leftJoin('snatched', 'claims.snatched_id', '=', 'snatched.id')
        ->leftJoin('torrents','claims.torrent_id','=','torrents.id')
        ->whereRaw("snatched.seedtime + claims.seed_time_begin >= ".($seedTimeRequiredHours*3600)." OR snatched.uploaded+ claims.uploaded_begin >=".($uploadedRequiredTimes."* torrents.size"));
    $reached_total = (clone $reached_list)->count();
    $reached_total_list = (clone $reached_list)->get();
    foreach ($reached_total_list as $row) {
        $reached_total_size = bcadd($reached_total_size, $row->torrent->size);
    }
    $claimResult['claim_reached_count'] = $reached_total;
    $claimResult['claim_reached_size'] = $reached_total_size;
    return $claimResult;
}

/**
 * @param array $claimResult
 * @param array $options
 * @return string[]
 */
function build_claim_table(array $claimResult = [], array $options = [])
{
    $table = sprintf('<table cellpadding="5" style="%s">', $options['table_style'] ?? '');
    $table .= '<tr>';
    $table .= sprintf('<td class="colhead">%s</td>', '认领数量');
    $table .= sprintf('<td class="colhead">%s</td>', '认领体积');
    $table .= sprintf('<td class="colhead">%s</td>', '达标数量');
    $table .= sprintf('<td class="colhead">%s</td>', '达标体积');
    $table .= '</tr>';

    $table .= sprintf(
        '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
        $claimResult['claim_count'],
        mksize($claimResult['claim_size']),
        $claimResult['claim_reached_count'],
        mksize($claimResult['claim_reached_size']),
    );

    $table .= '</table>';

    return [
        'table' => $table
    ];
}

/**
 * Calculate the admin claim based on the seed time required hours and uploaded required times.
 *
 * @param int $seedTimeRequiredHours The number of hours required for the seed time.
 * @param array $uploadedRequiredTimes An array of required times for uploaded claims.
 * @return array The admin claim result.
 */
function calculate_admin_claim($seedTimeRequiredHours, $uploadedRequiredTimes): array
{
    $query = \App\Models\Claim::query();
    $claim_uid_total_list = (clone $query)
        ->get()
        ->pluck('uid')
        ->toArray();
    $unique_uids = array_unique($claim_uid_total_list);;
    $AdminResult = [];
    foreach ($unique_uids as $uid) {
        $query = \App\Models\Claim::query()->where('uid', $uid);
        $row_result = [];
        $row_result['user_id'] = $uid;
        $user = \App\Models\User::query()->where('id', $uid)->first(\App\Models\User::$commonFields);
        $row_result['username'] = $user->username;
        $claimResult = calculate_claim($query, $seedTimeRequiredHours, $uploadedRequiredTimes);
        $row_result['claim_count'] = $claimResult['claim_count'];
        $row_result['claim_size'] = $claimResult['claim_size'];
        $row_result['claim_reached_count'] = $claimResult['claim_reached_count'];
        $row_result['claim_reached_size'] =  $claimResult['claim_reached_size'];
        array_push($AdminResult, $row_result);
    }
    return $AdminResult;
}

/**
 * Builds a claim admin table based on the given claim admin result and options.
 *
 * @param array $claimAdminResult An array containing the claim admin result.
 * @param array $options An array of options to customize the table.
 * @return array An array containing the generated table.
 */
function build_claim_admin_table(array $claimAdminResult = [], array $options = [])
{
    $table = sprintf('<table cellpadding="5" style="%s">', $options['table_style'] ?? '');
    $table .= '<tr>';
    $table .= sprintf('<td class="colhead">%s</td>', '保种人');
    $table .= sprintf('<td class="colhead">%s</td>', '保种总数量');
    $table .= sprintf('<td class="colhead">%s</td>', '达标数量');
    $table .= sprintf('<td class="colhead">%s</td>', '保种总体积');
    $table .= sprintf('<td class="colhead">%s</td>', '达标体积');
    $table .= sprintf('<td class="colhead">%s</td>', '是否达标');
    $table .= '</tr>';
    foreach ($claimAdminResult as $row) {
        $table .= sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            $row['username'],
            $row['claim_count'],
            $row['claim_reached_count'],
            mksize($row['claim_size']),
            mksize($row['claim_reached_size']),
            '-',
        );
    }
    $table .= '</table>';

    return [
        'table' => $table
    ];
}

if (!empty($_GET['torrent_id'])) {
    $torrentId = $_GET['torrent_id'];
    int_check($torrentId,true);
    $torrent = \App\Models\Torrent::query()->where('id', $torrentId)->first(\App\Models\Torrent::$commentFields);
    if (!$torrent) {
        stderr("Error", "Invalid torrent_id: $torrentId");
    }
    stdhead(nexus_trans('claim.title_for_torrent'));
    $query = \App\Models\Claim::query()->where('torrent_id', $torrentId);
    $pagerParam = "?torrent_id=$torrentId";
    print("<h1 align=center>".nexus_trans('claim.title_for_torrent') . "<a href=details.php?id=" . htmlspecialchars($torrentId) . "><b>&nbsp;".htmlspecialchars($torrent['name'])."</b></a></h1>");
} elseif (!empty($_GET['uid'])) {
    $uid = $_GET['uid'];
    int_check($uid,true);
    $user = \App\Models\User::query()->where('id', $uid)->first(\App\Models\User::$commonFields);
    if (!$user) {
        stderr("Error", "Invalid uid: $uid");
    }

    /*** 获取 seedtime 排序 url 参数 ***/
    $seedtime_order = $_GET['seedtime'];
    if ($seedtime_order && !($seedtime_order == 'asc' || $seedtime_order == 'desc')) {
        stderr("Error", "Invalid seedtime_order: $seedtime_order");
    }

    $unreached = $_GET['unreached'];
    if ($unreached && $unreached != '1') {
        stderr("Error", "Invalid unreached: $unreached");
    }

    $unseeding = $_GET['unseeding'];
    if ($unseeding && $unseeding != '1') {
        stderr("Error", "Invalid unseeding: $unseeding");
    }

    stdhead(nexus_trans('claim.title_for_user'));
    $query = \App\Models\Claim::query()->where('uid', $uid);
    $pagerParam = "?uid=$uid";
    print("<h1 align=center>".nexus_trans('claim.title_for_user') . "<a href=userdetails.php?id=" . htmlspecialchars($uid) . "><b>&nbsp;".htmlspecialchars($user->username)."</b></a></h1>");

    /*** 我的认领统计数据 ***/
    $claimResult = calculate_claim($query, $seedTimeRequiredHours, $uploadedRequiredTimes);
    $claimTableResult = build_claim_table($claimResult, ['table_style' => 'width: 50%']);
    print '<div style="display: flex;justify-content: center;margin-top: 20px;">'.$claimTableResult['table'].'</div>';

    /*** 保种管理员查看保种组统计数据 ***/
    if(get_user_class() >= UC_ADMINISTRATOR) {
        print "<h3>保种组数据</h3>";
        $claimAdminResult = calculate_admin_claim($seedTimeRequiredHours, $uploadedRequiredTimes);
        $claimAdminTableResult = build_claim_admin_table($claimAdminResult, ['table_style' => 'width: 50%']);
        print '<div style="display: flex;justify-content: center;margin-bottom: 30px;">'.$claimAdminTableResult['table'].'</div>';
    }

    /*** 输出排序类型子菜单 ***/
    $active_color = "#ff8e00";
    $active_style = "style='color: $active_color'";
    $is_default = !$seedtime_order && !$unreached && !$unseeding;
    $default = "<a href='claim.php$pagerParam' " .($is_default ? $active_style : ""). ">默认</a> | ";
    $seedtime_order_asc = "<a href='claim.php$pagerParam&seedtime=asc' " .($seedtime_order == "asc" ? $active_style : ""). ">按做种时长升序排列</a> | ";
    $seedtime_order_desc = "<a href='claim.php$pagerParam&seedtime=desc' " .($seedtime_order == "desc" ? $active_style : ""). ">按做种时长降序排列</a> | ";
    $unreached_html = "<a href='claim.php$pagerParam&unreached=1' " .($unreached == "1" ? $active_style : ""). ">本月未达标</a> | ";
    $unseeding_html = "<a href='claim.php$pagerParam&unseeding=1' " .($unseeding == "1" ? $active_style : ""). ">未做种</a>";
    $MENU = <<<HTML
        <br><b>
            {$default}
            {$seedtime_order_asc}
            {$seedtime_order_desc}
            {$unreached_html}
            {$unseeding_html}
        </b><br><br>
    HTML;
    echo $MENU;

    if ($uid == $CURUSER['id']) {
        $actionTh = sprintf("<td class='colhead' align='center'>%s</td>", nexus_trans("claim.th_action"));
    }
} else {
    stderr("Invalid parameters", "Require torrent_id or uid");
}

begin_main_frame();
$total = (clone $query)->count();
$final_pager_param = "$pagerParam&"
    .($seedtime_order ? "seedtime=$seedtime_order&" : "")
    .($unreached ? "unreached=$unreached&" : "")
    .($unseeding ? "unseeding=$unseeding&" : "");

$page_size = 50;
list($pagertop, $pagerbottom, $limit, $offset, $pageSize) = pager($page_size, $total, $final_pager_param);

$list =[];
if (!empty($_GET['seedtime'])) {
    /*** 按本月做种时长排序 ***/
    $list = (clone $query)->with(['user', 'torrent', 'snatch'])
        ->select("claims.*")
        ->leftJoin('snatched', 'claims.snatched_id', '=', 'snatched.id')
        ->offset($offset)
        ->limit($pageSize)
        ->orderByRaw('snatched.seedtime - seed_time_begin '.$seedtime_order)
        ->get();
} elseif (!empty($_GET['unreached'])) {
    try {
        /*** 过滤出本月未达标的数据 ***/
        $list = (clone $query)
            ->with(['user', 'torrent', 'snatch'])
            ->select("claims.*")
            ->leftJoin('snatched', 'claims.snatched_id', '=', 'snatched.id')
            ->leftJoin('torrents','claims.torrent_id','=','torrents.id')
            ->whereRaw("snatched.seedtime + claims.seed_time_begin < ".($seedTimeRequiredHours*3600)." AND snatched.uploaded+ claims.uploaded_begin <".($uploadedRequiredTimes."* torrents.size"));
        $total = (clone $list)->count();
        list($pagertop, $pagerbottom, $limit, $offset, $pageSize) = pager($page_size, $total, $final_pager_param);

        $list = $list
            ->offset($offset)
            ->limit($pageSize)
            ->orderBy('claims.id', 'desc')
            ->get();
    } catch (Exception $e) {
        // 捕获异常并打印错误信息到页面
        echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
} elseif (!empty($_GET['unseeding'])) {
    try {
        /*** 过滤出本月所有已认领未做种数据 ***/
        $list = (clone $query)
            ->whereRaw('not exists (select * from `peers` where `claims`.`torrent_id` = `peers`.`torrent` and `seeder` = "yes")');
        $total = (clone $list)->count();
        list($pagertop, $pagerbottom, $limit, $offset, $pageSize) = pager($page_size, $total, $final_pager_param);

        $list = $list
            ->with(['user', 'torrent', 'snatch'])
            ->offset($offset)
            ->limit($pageSize)
            ->orderBy('id', 'desc')
            ->get();
    } catch (Exception $e) {
        // 捕获异常并打印错误信息到页面
        echo 'Caught exception: ',  $e->getMessage(), "\n";
    }
} else {
    $list = (clone $query)
        ->with(['user', 'torrent', 'snatch'])
        ->offset($offset)
        ->limit($pageSize)
        ->orderBy('id', 'desc')
        ->get();
}

print("<table id='claim-table' width='100%'>");

$default_type = true;
$is_seeding_header = $default_type ? "<td class='colhead' align='center'>做种中</td>" : "";

print("<tr>
    <td class='colhead' align='center'>".nexus_trans('claim.th_id')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_username')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_torrent_name')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_torrent_size')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_torrent_ttl')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_claim_at')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_last_settle')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_seed_time_this_month')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_uploaded_this_month')."</td>
    <td class='colhead' align='center'>".nexus_trans('claim.th_reached_or_not')."</td>
    ".$is_seeding_header."
    ".$actionTh."
</tr>");
$now = \Carbon\Carbon::now();
$claimRep = new \App\Repositories\ClaimRepository();
foreach ($list as $row) {
    if (
        bcsub($row->snatch->seedtime, $row->seed_time_begin) >= $seedTimeRequiredHours * 3600
        || bcsub($row->snatch->uploaded, $row->uploaded_begin) >= $uploadedRequiredTimes * $row->torrent->size
    ) {
        $reached = 'Yes';
    } else {
        $reached = 'No';
    }
    $actionTd = '';
    if ($actionTh) {
        $actionTd = sprintf('<td class="rowfollow nowrap" align="center">%s</td>', $claimRep->buildActionButtons($row->torrent_id, $row, 1));
    }

//    echo "<pre>";
//    var_export($row->peers); echo "<br>";
//    echo "</pre>";

    $is_seeding_cell = $default_type ? "<td class='rowfollow nowrap' align='center'>". ($row->is_seeding ? "<div style='background-color: green; color: green'>Y</div>" : "<div style='background-color: red; color: red'>N</div>") ."</td>" : "";
    print("<tr>
        <td class='rowfollow nowrap' align='center'>" . $row->id . "</td>
        <td class='rowfollow' align='left'><a href='userdetails.php?id=" . $row->uid . "'>" . $row->user->username . "</a></td>
        <td class='rowfollow' align='left'><a href='details.php?id=" . $row->torrent_id . "'>" . $row->torrent->name . "</a></td>
        <td class='rowfollow nowrap' align='center'>" . mksize($row->torrent->size) . "</td>
        <td class='rowfollow nowrap' align='center'>" . mkprettytime($row->torrent->added->diffInSeconds($now)) . "</td>
        <td class='rowfollow nowrap' align='center'>" . format_datetime($row->created_at) . "</td>
        <td class='rowfollow nowrap' align='center'>" . format_datetime($row->last_settle_at) . "</td>
        <td class='rowfollow nowrap' align='center'>" . mkprettytime($row->snatch->seedtime - $row->seed_time_begin) . "</td>
        <td class='rowfollow nowrap' align='center'>" . mksize($row->snatch->uploaded - $row->uploaded_begin) . "</td>
        <td class='rowfollow nowrap' align='center'>" . $reached . "</td>
        ".$is_seeding_cell."
        ".$actionTd."
    </tr>");
}

print("</table>");
print($pagerbottom);
end_main_frame();
stdfoot();


