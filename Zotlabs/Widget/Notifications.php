<?php

namespace Zotlabs\Widget;

class Notifications {

	function widget($arr) {

		$channel = \App::get_channel();

		if(local_channel()) {
			$notifications[] = [
				'type' => 'network',
				'icon' => 'th',
				'severity' => 'secondary',
				'label' => t('New Network Activity'),
				'title' => t('New Network Activity Notifications'),
				'viewall' => [
					'url' => 'network',
					'label' => t('View your network activity')
				],
				'markall' => [
					'label' => t('Mark all notifications read')
				],
				'filter' => [
					'label' => t('Show new posts only')
				]
			];

			$notifications[] = [
				'type' => 'home',
				'icon' => 'home',
				'severity' => 'danger',
				'label' => t('New Home Activity'),
				'title' => t('New Home Activity Notifications'),
				'viewall' => [
					'url' => 'channel/' . $channel['channel_address'],
					'label' => t('View your home activity')
				],
				'markall' => [
					'label' => t('Mark all notifications seen')
				],
				'filter' => [
					'label' => t('Show new posts only')
				]
			];

			$notifications[] = [
				'type' => 'mail',
				'icon' => 'envelope',
				'severity' => 'danger',
				'label' => t('New Mails'),
				'title' => t('New Mails Notifications'),
				'viewall' => [
					'url' => 'mail/combined',
					'label' => t('View your private mails')
				],
				'markall' => [
					'label' => t('Mark all messages seen')
				]
			];

			$notifications[] = [
				'type' => 'all_events',
				'icon' => 'calendar',
				'severity' => 'secondary',
				'label' => t('New Events'),
				'title' => t('New Events Notifications'),
				'viewall' => [
					'url' => 'mail/combined',
					'label' => t('View events')
				],
				'markall' => [
					'label' => t('Mark all events seen')
				]
			];

			$notifications[] = [
				'type' => 'intros',
				'icon' => 'users',
				'severity' => 'danger',
				'label' => t('New Connections'),
				'title' => t('New Connections Notifications'),
				'viewall' => [
					'url' => 'connections',
					'label' => t('View all connections')
				]
			];

			$notifications[] = [
				'type' => 'files',
				'icon' => 'folder',
				'severity' => 'danger',
				'label' => t('New Files'),
				'title' => t('New Files Notifications'),
			];

			$notifications[] = [
				'type' => 'notify',
				'icon' => 'exclamation',
				'severity' => 'danger',
				'label' => t('Notices'),
				'title' => t('Notices'),
				'viewall' => [
					'url' => 'notifications/system',
					'label' => t('View all notices')
				],
				'markall' => [
					'label' => t('Mark all notices seen')
				]
			];
		}

		if(local_channel() && is_site_admin()) {
			$notifications[] = [
				'type' => 'register',
				'icon' => 'user-o',
				'severity' => 'danger',
				'label' => t('New Registrations'),
				'title' => t('New Registrations Notifications'),
			];
		}

		if(get_config('system', 'disable_discover_tab') != 1) {
			$notifications[] = [
				'type' => 'pubs',
				'icon' => 'globe',
				'severity' => 'secondary',
				'label' => t('Public Stream'),
				'title' => t('Public Stream Notifications'),
				'viewall' => [
					'url' => 'pubstream',
					'label' => t('View the public stream')
				],
				'markall' => [
					'label' => t('Mark all notifications seen')
				],
				'filter' => [
					'label' => t('Show new posts only')
				]
			];
		}

		$o = replace_macros(get_markup_template('notifications_widget.tpl'), array(
			'$module' => \App::$module,
			'$notifications' => $notifications,
			'$no_notifications' => t('Sorry, you have got no notifications at the moment'),
			'$loading' => t('Loading')
		));

		return $o;

	}
}
 
