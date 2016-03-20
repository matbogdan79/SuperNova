<?php

/**
 * Class Unit
 *
 * @property int $unitId
 * @property int $count
 * @method int getCount() - TODO - DEPRECATED - не существует, но используется в UBE
 * @method int getUnitId()
 * @method int getType()
 * @method int getTimeStart()
 * @method int getTimeFinish()
 * @see Unit::__get()
 *
 */
class Unit extends DBRowLocatedAtParent {


  // DBRow inheritance *************************************************************************************************

  /**
   * Table name in DB
   *
   * @var string
   */
  protected static $_table = 'unit';
  /**
   * Name of ID field in DB
   *
   * @var string
   */
  protected static $_dbIdFieldName = 'unit_id';
  /**
   * DB_ROW to Class translation scheme
   *
   * @var array
   */
  protected static $_scheme = array(
    'dbId'          => array(
      P_DB_FIELD => 'unit_id',
    ),

    // Location data is taken from container
    'playerOwnerId' => array(
      P_DB_FIELD    => 'unit_player_id',
      P_METHOD_INJECT => 'injectLocation',
      P_READ_ONLY   => true,
    ),
//    'locationType' => array(
//      P_DB_FIELD  => 'unit_location_type',
//      P_READ_ONLY => true,
//    ),
//    'locationDbId' => array(
//      P_DB_FIELD  => 'unit_location_id',
//      P_READ_ONLY => true,
//    ),

    'type'   => array(
      P_DB_FIELD   => 'unit_type',
      P_FUNC_INPUT => 'intval',
    ),
    'unitId' => array(
      P_DB_FIELD   => 'unit_snid',
      P_METHOD_SET => 'setUnitId',
      P_FUNC_INPUT => 'intval',
    ),
    'count'  => array(
      P_DB_FIELD   => 'unit_level',
      P_FUNC_INPUT => 'floatval',
    ),

    'timeStart'  => array(
      P_DB_FIELD    => 'unit_time_start',
      P_FUNC_INPUT  => 'sqlStringToUnixTimeStamp',
      P_FUNC_OUTPUT => 'unixTimeStampToSqlString',
    ),
    'timeFinish' => array(
      P_DB_FIELD    => 'unit_time_finish',
      P_FUNC_INPUT  => 'sqlStringToUnixTimeStamp',
      P_FUNC_OUTPUT => 'unixTimeStampToSqlString',
    ),
  );

  public function __construct() {
    parent::__construct();
    $this->unit_bonus = new Bonus();
  }

  /**
   * Является ли юнит пустым - т.е. при исполнении dbSave должен быть удалён
   *
   * @return bool
   */
  public function isEmpty() {
    return $this->count <= 0;
  }



  // New statics *******************************************************************************************************

  /**
   * @var bool
   */
  protected static $_is_static_init = false;
  /**
   * @var string
   */
  protected static $_sn_group_name = '';
  /**
   * @var array
   */
  protected static $_group_unit_id_list = array();

  /**
   * Статический иницилизатор. ДОЛЖЕН БЫТЬ ВЫЗВАН ПЕРЕД ИСПОЛЬЗВОАНИЕМ КЛАССА!
   *
   * @param string $group_name
   */
  public static function _init($group_name = '') {
    if(static::$_is_static_init) {
      return;
    }

    if($group_name) {
      static::$_sn_group_name = $group_name;
    }

    if(static::$_sn_group_name) {
      static::$_group_unit_id_list = sn_get_groups(static::$_sn_group_name);
      empty(static::$_group_unit_id_list) ? static::$_group_unit_id_list = array() : false;
    }

  }

  /**
   * Проверяет - принадлежит ли указанный ID юнита данной группе
   *
   * @param int $unit_id
   *
   * @return bool
   */
  public static function is_in_group($unit_id) {
    return isset(static::$_group_unit_id_list[$unit_id]);
  }


  // Properties from fields ********************************************************************************************

  protected $unitId = 0;
  // TODO - Type is extracted on-the-fly from $info
  protected $type = 0;

  public function setUnitId($unitId) {
    // TODO - Reset combat stats??
    $this->unitId = $unitId;

    if($this->unitId) {
      $this->info = get_unit_param($this->unitId);
      $this->type = $this->info[P_UNIT_TYPE];
    } else {
      $this->info = array();
    }
  }


  protected $count = 0;

  public function setCount($value) {
    // TODO - Reset combat stats??
    if($value < 0) {
      classSupernova::$debug->error('Can not set Unit::$count to negative value');
    }
    $this->count = $value;

    return $this->count;
  }

  /**
   * @param int $value
   *
   * @return int
   */
  // TODO - some calcs ??????
  public function adjustCount($value) {
    if($this->count + $value < 0) {
      classSupernova::$debug->error('Can not let Unit::$count value be less then a zero - adjustCount with negative greater then $count');
    }
    $this->count += $value;

    return $this->count;
  }

  /**
   * Extracts resources value from db_row
   *
   * @param array $db_row
   *
   * @internal param Unit $that
   * @version 41a6.12
   */
  protected function injectLocation(array &$db_row) {
    $db_row['unit_player_id'] = $this->getPlayerOwnerId();
    $db_row['unit_location_type'] = $this->getLocationType();
    $db_row['unit_location_id'] = $this->getLocationDbId();
  }


  protected $timeStart = 0;
  protected $timeFinish = 0;


  // Internal properties ***********************************************************************************************

  /**
   * Passport info per unit
   *
   * @var array $info
   */
  public $info = array();

  /**
   * @var Bonus $unit_bonus
   */
  public $unit_bonus = null;


  // TODO - __GET, __SET, __IS_NULL, __EMPTY - короче, магметоды
  // А еще нужны методы для вытаскивания ЧИСТОГО и БОНУСНОГО значений
  // Магметоды вытаскивают чистые значения. А если нам нужны бонусные - вытаскивают их спецметоды ??? Хотя бонусные вроде используются чаще...
  // Наоборот - для совместимости с MRC_GET_LEVEL()


  // TODO - DEBUG
  public function zeroDbId() {
    $this->dbId = 0;
  }
}