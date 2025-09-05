<?php
// File: includes/calendar.php
// Purpose: Functions to generate a monthly calendar.

function generate_calendar($month, $year, $appointments = []) {
    // Create a new DateTime object for the first day of the month.
    $first_day = new DateTime("$year-$month-01");

    // Get the number of days in the month.
    $days_in_month = (int)$first_day->format('t');

    // Get the day of the week for the first day of the month (1 for Monday, 7 for Sunday).
    $day_of_week = (int)$first_day->format('N');

    // Create the calendar table.
    $calendar = '<table class="calendar">';
    $calendar .= '<caption>' . $first_day->format('F Y') . '</caption>';
    $calendar .= '<thead><tr><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th><th>Sun</th></tr></thead>';
    $calendar .= '<tbody><tr>';

    // Add empty cells for the days before the first day of the month.
    for ($i = 1; $i < $day_of_week; $i++) {
        $calendar .= '<td></td>';
    }

    // Add the days of the month.
    for ($day = 1; $day <= $days_in_month; $day++) {
        $current_date = "$year-$month-" . str_pad($day, 2, '0', STR_PAD_LEFT);
        $is_today = ($current_date === date('Y-m-d'));
        $class = $is_today ? 'today' : '';

        // Check for appointments
        $daily_appointments = [];
        if (isset($appointments[$current_date])) {
            $daily_appointments = $appointments[$current_date];
            $class .= ' has-appointment';
        }

        $calendar .= '<td class="' . $class . '">';
        $calendar .= '<div class="day-number">' . $day . '</div>';

        if (!empty($daily_appointments)) {
            $calendar .= '<div class="appointments">';
            foreach ($daily_appointments as $appointment) {
                $calendar .= '<div class="appointment" title="' . htmlspecialchars($appointment['description']) . '">' . htmlspecialchars($appointment['title']) . '</div>';
            }
            $calendar .= '</div>';
        }
        $calendar .= '</td>';


        // If it's the end of the week, start a new row.
        if (($day_of_week + $day - 1) % 7 === 0) {
            $calendar .= '</tr><tr>';
        }
    }

    // Add empty cells for the remaining days of the week.
    while (($day_of_week + $days_in_month - 1) % 7 !== 0) {
        $calendar .= '<td></td>';
        $day_of_week++;
    }


    $calendar .= '</tr></tbody>';
    $calendar .= '</table>';

    return $calendar;
}
