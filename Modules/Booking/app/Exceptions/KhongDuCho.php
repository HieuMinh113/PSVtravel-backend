<?php

namespace Modules\Booking\Exceptions;

use RuntimeException;

/** Đợt khởi hành không còn đủ chỗ cho số khách của đơn — không lưu thay đổi. */
class KhongDuCho extends RuntimeException {}
