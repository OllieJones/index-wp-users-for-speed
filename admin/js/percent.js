jQuery(document).on('heartbeat-send', function (event, data) {
    data.index_wp_users_for_speed_percent = 'poll';
    console.log ('heartbeat');
});
jQuery(document).on('heartbeat-tick', function (event, data) {
    console.log ('heartbeat', data);
    let percent = Number.parseFloat(data.index_wp_users_for_speed_percent) * 100;
    if (typeof percent === 'number' && ! isNaN(percent)) {
        percent = percent.toFixed(0);
        const percentElement = document.querySelector("div.notice.index-wp-users-for-speed span.percent");
        if (percentElement) {
            percentElement.textContent = percent;
        }
    }
});