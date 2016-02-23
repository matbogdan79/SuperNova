<?php

class UBEMoonCalculator {
  /**
   * @var int
   */
  protected $status = UBE_MOON_NONE;
  /**
   * @var string
   */
  protected $moon_name = ''; // TODO - UNUSED! USE IT, LUKE!
  /**
   * @var int
   */
  protected $create_chance = 0;
  /**
   * @var int
   */
  protected $moon_diameter = 0;

  /**
   * Что случилось с кораблями, которые могли уничтожить луну
   *
   * @var int
   */
  protected $reapers_status = UBE_MOON_REAPERS_NONE;

  /**
   * Шанс уничтожить луну
   *
   * @var int
   */
  protected $destroy_chance = 0;

  /**
   * Шанс взрыва кораблей, уничтожающих луну
   *
   * @var int
   */
  protected $reaper_die_chance = 0;

  public function __construct() {
    $this->status = UBE_MOON_NONE;
    $this->moon_name = '';
    $this->create_chance = 0;
    $this->moon_diameter = 0;
    $this->reapers_status = UBE_MOON_REAPERS_NONE;
    $this->destroy_chance = 0;
    $this->reaper_die_chance = 0;
  }

  public function load_from_report($report_row) {
    $this->status = $report_row['ube_report_moon'];
    $this->moon_name = ''; // TODO - load name
    $this->create_chance = $report_row['ube_report_moon_chance'];
    $this->moon_diameter = $report_row['ube_report_moon_size'];
    $this->reapers_status = $report_row['ube_report_moon_reapers'];
    $this->destroy_chance = $report_row['ube_report_moon_destroy_chance'];
    $this->reaper_die_chance = $report_row['ube_report_moon_reapers_die_chance'];
  }

  /**
   * @param UBEDebris $debris
   * @param bool      $is_simulator
   */
  protected function moon_create_try(UBEDebris $debris, $is_simulator = false) {
    $this->status = UBE_MOON_NONE;

    $debris_for_moon = $debris->debris_total();

    if(!$debris_for_moon) {
      return;
    }

    // TODO uni_calculate_moon_chance
    $moon_chance = min($debris_for_moon / UBE_MOON_DEBRIS_PER_PERCENT, UBE_MOON_PERCENT_MAX); // TODO Configure
    $moon_chance = $moon_chance >= UBE_MOON_PERCENT_MIN ? $moon_chance : 0;
    $this->create_chance = $moon_chance;
    if($moon_chance) {
      if($is_simulator || mt_rand(1, 100) <= $moon_chance) {
        $this->status = UBE_MOON_CREATE_SUCCESS;
        $this->moon_diameter = round($is_simulator ? $moon_chance * 150 + 1999 : mt_rand($moon_chance * 100 + 1000, $moon_chance * 200 + 2999));

        if($debris_for_moon <= UBE_MOON_DEBRIS_MAX_SPENT) {
          $debris->debris_reset();
        } else {
          $moon_debris_left_percent = ($debris_for_moon - UBE_MOON_DEBRIS_MAX_SPENT) / $debris_for_moon;
          $debris->debris_adjust_proportional($moon_debris_left_percent);
        }
      } else {
        $this->status = UBE_MOON_CREATE_FAILED;
      }
    }
  }

  /**
   * @param int $reapers
   */
  protected function moon_destroy_try($reapers) {
    // TODO: $is_simulator
    if($reapers <= 0) {
      return;
    }

    $random = mt_rand(1, 100);
    $this->destroy_chance = max(1, min(99, round((100 - sqrt($this->moon_diameter)) * sqrt($reapers))));
    $this->reaper_die_chance = round(sqrt($this->moon_diameter) / 2 + sqrt($reapers));
    $this->status = $random <= $this->destroy_chance ? UBE_MOON_DESTROY_SUCCESS : UBE_MOON_DESTROY_FAILED;
    $random = mt_rand(1, 100);
    $this->reapers_status = $random <= $this->reaper_die_chance ? UBE_MOON_REAPERS_DIED : UBE_MOON_REAPERS_RETURNED;
  }


  protected function calculate_reapers(UBE $ube) {
    $objRound = $ube->rounds->get_last_element();
    $reapers = 0;
    foreach($objRound->round_fleets as $fleet_id => $fleet_data) {
      if($objRound->fleet_info[$fleet_id]->UBE_FLEET_TYPE == UBE_ATTACKERS) {
        foreach($fleet_data[UBE_COUNT] as $unit_id => $unit_count) {
          // TODO: Работа по группам - группа "Уничтожители лун"
          $reapers += ($unit_id == SHIP_HUGE_DEATH_STAR) ? $unit_count : 0;
        }
      }
    }

    return $reapers;
  }

  public function calculate_moon(UBE $ube) {
    if(UBE_MOON_EXISTS == $this->status) {
      if($ube->mission_type_id == MT_DESTROY && $ube->combat_result == UBE_COMBAT_RESULT_WIN) {
        $this->moon_destroy_try($this->calculate_reapers($ube));
      }
    } else {
      $this->moon_create_try($ube->debris, $ube->is_simulator);
    }
  }

  public function report_generate_sql() {
    return "`ube_report_moon` = " . (int)$this->status . ",
      `ube_report_moon_chance` = " . (int)$this->create_chance . ",
      `ube_report_moon_size` = " . (float)$this->moon_diameter . ",
      `ube_report_moon_reapers` = " . (int)$this->reapers_status . ",
      `ube_report_moon_destroy_chance` = " . (int)$this->destroy_chance . ",
      `ube_report_moon_reapers_die_chance` = " . (int)$this->reaper_die_chance;
  }

  public function template_generate_array() {
    return array(
      // Data
      'UBE_MOON' => $this->status,
      'UBE_MOON_CHANCE' => round($this->create_chance, 2),
      'UBE_MOON_SIZE' => $this->moon_diameter,
      'UBE_MOON_REAPERS' => $this->reapers_status,
      'UBE_MOON_DESTROY_CHANCE' => $this->destroy_chance,
      'UBE_MOON_REAPERS_DIE_CHANCE' => $this->reaper_die_chance,

      // Constants
      'UBE_MOON_EXISTS' => UBE_MOON_EXISTS,
      'UBE_MOON_NONE' => UBE_MOON_NONE,
      'UBE_MOON_CREATE_SUCCESS' => UBE_MOON_CREATE_SUCCESS,
      'UBE_MOON_CREATE_FAILED' => UBE_MOON_CREATE_FAILED,
      'UBE_MOON_REAPERS_NONE' => UBE_MOON_REAPERS_NONE,
      'UBE_MOON_DESTROY_SUCCESS' => UBE_MOON_DESTROY_SUCCESS,
      'UBE_MOON_REAPERS_RETURNED' => UBE_MOON_REAPERS_RETURNED,
    );
  }

  /**
   * @param $planet_info
   * @param $destination_user_id
   * @param $planet_id
   */
  public function db_apply_result($planet_info, $destination_user_id, $planet_id) {
    if($this->status == UBE_MOON_CREATE_SUCCESS) {
      $moon_row = uni_create_moon($planet_info[PLANET_GALAXY], $planet_info[PLANET_SYSTEM], $planet_info[PLANET_PLANET], $destination_user_id, $this->moon_diameter, '', false);
      $this->moon_name = $moon_row['name'];
      unset($moon_row);
    } elseif($this->status == UBE_MOON_DESTROY_SUCCESS) {
      db_planet_delete_by_id($planet_id);
    }
  }

  public function message_generate(UBE $ube) {
    global $lang;

    $text_defender = '';
    if($this->status == UBE_MOON_CREATE_SUCCESS) {
      $text_defender .= "{$lang['ube_report_moon_created']} {$this->moon_diameter} {$lang['sys_kilometers_short']}<br /><br />";
    } elseif($this->status == UBE_MOON_CREATE_FAILED) {
      $text_defender .= "{$lang['ube_report_moon_chance']} {$this->create_chance}%<br /><br />";
    }

    if($ube->mission_type_id == MT_DESTROY) {
      if($this->reapers_status == UBE_MOON_REAPERS_NONE) {
        $text_defender .= $lang['ube_report_moon_reapers_none'];
      } else {
        $text_defender .= "{$lang['ube_report_moon_reapers_wave']}. {$lang['ube_report_moon_reapers_chance']} {$this->destroy_chance}%. ";
        $text_defender .= $lang[$this->status == UBE_MOON_DESTROY_SUCCESS ? 'ube_report_moon_reapers_success' : 'ube_report_moon_reapers_failure'] . "<br />";

        $text_defender .= "{$lang['ube_report_moon_reapers_outcome']} {$this->reaper_die_chance}%. ";
        $text_defender .= $lang[$this->reapers_status == UBE_MOON_REAPERS_RETURNED ? 'ube_report_moon_reapers_survive' : 'ube_report_moon_reapers_died'];
      }
      $text_defender .= '<br /><br />';
    }

    return $text_defender;
  }

  public function get_status() {
    return $this->status;
  }

  public function get_reapers_status() {
    return $this->reapers_status;
  }

  public function load_status($destination_planet) {
    if($destination_planet['planet_type'] == PT_MOON || is_array(db_planet_by_parent($destination_planet['id'], true, '`id`'))) {
      $this->status = UBE_MOON_EXISTS;
      $this->moon_diameter = $destination_planet['diameter'];
      $this->reapers_status = UBE_MOON_REAPERS_NONE;
    } else {
      // По умолчанию: нет луны итд
    }
  }

}