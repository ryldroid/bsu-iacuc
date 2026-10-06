<?php

trait PublicPageData
{
  // ===== HOME PAGE DATA =====
  protected function homePageData(): array
  {
    return [
      'siteSettings'        => (new SiteSettingModel())->getAll(),
      'faqs'                => (new FaqModel())->getAll(),
      'latestAnnouncements' => array_slice((new AnnouncementModel())->getAll(), 0, 3),
    ];
  }

  // ===== ANNOUNCEMENTS PAGE DATA =====
  protected function announcementsPageData(): array
  {
    return [
      'officeAnnouncements' => (new AnnouncementModel())->getAll(),
    ];
  }

  // ===== CONTACT PAGE DATA =====
  protected function contactPageData(): array
  {
    return [
      'offices' => (new ContactOfficeModel())->getAll(),
    ];
  }
}
