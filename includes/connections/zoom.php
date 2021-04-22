<?php

namespace GroundhoggBookingCalendar\Connections;

use Groundhogg\Plugin;
use function Groundhogg\array_map_with_keys;
use function Groundhogg\get_array_var;
use function Groundhogg\remote_post_json;

class Zoom extends Base {

	public function slug() {
		return 'zoom';
	}

	public function endpoint_base() {
		return GROUNDHOGG_BOOKING_CALENDAR_ZOOM_BASE_URL;
	}
}
