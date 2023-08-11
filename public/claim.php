<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
$torrentId = $uid = 0;
$actionTh = $actionTd = '';

$seedtime_order = ''; // 增加按本月做种时长排序

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

    stdhead(nexus_trans('claim.title_for_user'));
    $query = \App\Models\Claim::query()->where('uid', $uid);
    $pagerParam = "?uid=$uid";
    print("<h1 align=center>".nexus_trans('claim.title_for_user') . "<a href=userdetails.php?id=" . htmlspecialchars($uid) . "><b>&nbsp;".htmlspecialchars($user->username)."</b></a></h1>");

    /*** 输出排序类型子菜单 ***/
    $active_color = "#ff8e00";
    $active_style = "style='color: $active_color'";
    $default = "<a href='claim.php$pagerParam' " .($seedtime_order == "" ? $active_style : ""). ">默认</a> | ";
    $seedtime_order_asc = "<a href='claim.php$pagerParam&seedtime=asc' " .($seedtime_order == "asc" ? $active_style : ""). ">按做种时长升序排列</a> | ";
    $seedtime_order_desc = "<a href='claim.php$pagerParam&seedtime=desc' " .($seedtime_order == "desc" ? $active_style : ""). ">按做种时长降序排列</a>";
    $MENU = <<<HTML
        <br><b>
            {$default}
            {$seedtime_order_asc}
            {$seedtime_order_desc}
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
list($pagertop, $pagerbottom, $limit, $offset, $pageSize) = pager(50, $total, "$pagerParam&");

$list =[];
if (empty($_GET['seedtime'])) {
    $list = (clone $query)->with(['user', 'torrent', 'snatch'])->offset($offset)->limit($pageSize)->orderBy('id', 'desc')->get();
} else {
    /*** 按本月做种时长排序 ***/
    $list = (clone $query)->with(['user', 'torrent', 'snatch'])
        ->join('snatched', 'claims.snatched_id', '=', 'snatched.id')
        ->offset($offset)
        ->limit($pageSize)
        ->orderByRaw('snatched.seedtime - seed_time_begin '.$seedtime_order)
        ->get();
}

print("<table id='claim-table' width='100%'>");
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
    ".$actionTh."
</tr>");
$now = \Carbon\Carbon::now();
$seedTimeRequiredHours = \App\Models\Claim::getConfigStandardSeedTimeHours();
$uploadedRequiredTimes = \App\Models\Claim::getConfigStandardUploadedTimes();
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
        ".$actionTd."
    </tr>");
}

print("</table>");
print($pagerbottom);
end_main_frame();
stdfoot();


