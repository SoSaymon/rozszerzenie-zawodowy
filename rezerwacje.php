<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>KINO „Za rogiem”</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f0f0f0;
        }

        form {
            margin: 20px auto;
            width: 300px;
            padding: 20px;
            border: 1px solid #ccc;
            background-color: #fff;
        }

        label {
            display: block;
            margin-bottom: 10px;
        }

        input[type="date"], input[type="time"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 20px;
            border: 1px solid #ccc;
        }

        input[type="submit"] {
            padding: 10px 20px;
            border: none;
            background-color: #007BFF;
            color: #fff;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #0056b3;
        }

        table {
            margin: 20px auto;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
        }

        .occupied {
            background-color: red;
        }

        .free {
            background-color: green;
        }

        .selected {
            background-color: yellow;
        }
        .yours {
            background-color: blue;
        }
    </style>
</head>
<body>
<form action="rezerwacje.php" method="post">
    <label for="date">Data:</label><br>
    <input type="date" id="date" name="date"><br>
    <label for="time">Godzina:</label><br>
    <input type="time" id="time" name="time"><br>
    <input type="submit" value="Pokaż">
</form>
<form action="rezerwacje.php" method="post">
    <label for="check_name">Imię:</label>
    <input type="text" id="check_name" name="check_name" required>
    <label for="check_surname">Nazwisko:</label>
    <input type="text" id="check_surname" name="check_surname" required>
    <input type="submit" value="Sprawdź rezerwacje">
</form>
<?php
$reserved_seats = array();
if (isset($_COOKIE['date']) && isset($_COOKIE['time'])) {
    echo "<script>document.getElementById('date').value = '" . $_COOKIE['date'] . "';</script>";
    echo "<script>document.getElementById('time').value = '" . $_COOKIE['time'] . "';</script>";
    echo '<form action="rezerwacje.php" method="post">
            <label for="name">Imię:</label>
            <input type="text" name="name" required>
            <label for="surname">Nazwisko:</label>
            <input type="text" name="surname" required>
            <input type="submit" value="Rezerwuj">
            </form>';
}

$mysqli = new mysqli('localhost', 'root', '', 'kino');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8");

$hall = array_fill(0, 15, array_fill(0, 20, 0));

if (isset($_POST["check_name"]) && isset($_POST["check_surname"])) {
    $stmt = $mysqli->prepare("SELECT `Data`, `Godzina`, `Rzad`, `Miejsce` FROM `rezerwacje` INNER JOIN `users` ON rezerwacje.id_user = users.id_user WHERE users.name = ? AND users.surname = ?");
    $stmt->bind_param('ss', $_POST['check_name'], $_POST['check_surname']);

    $stmt->execute();

    $stmt->bind_result($date, $time, $row, $seat);

    setcookie("date", $date, time() + 3600, "/");
    setcookie("time", $time, time() + 3600, "/");

    echo "Rezerwacje dla " . $_POST['check_name'] . " " . $_POST['check_surname'] . ":<br>";
    $reserved_seats = array();
    while ($stmt->fetch()) {
        echo "Data: " . $date . ", Godzina: " . $time . ", Rząd: " . $row . ", Miejsce: " . $seat . "<br>";
        $reserved_seats[] = ($row - 1) * 20 + $seat;
    }
    $stmt->close();
}

if (isset($_POST["name"] ) && isset($_POST["surname"]) && isset($_COOKIE['selectedSeats'])) {
    $stmt = $mysqli->prepare("INSERT INTO users (name, surname) VALUES (?, ?)");
    $stmt->bind_param('ss', $_POST['name'], $_POST['surname']);

    $stmt->execute();

    $last_id = $mysqli->insert_id;

    $stmt->close();

    $selectedSeats = json_decode($_COOKIE['selectedSeats'], true);

    $stmt = $mysqli->prepare("INSERT INTO rezerwacje (id_user, Data, Godzina, Rzad, Miejsce) VALUES (?, ?, ?, ?, ?)");

    foreach ($selectedSeats as $seatId) {
        $row = floor(($seatId - 1) / 20) + 1;
        $seat = ($seatId - 1) % 20 + 1;

        $stmt->bind_param('isssi', $last_id, $_COOKIE['date'], $_COOKIE['time'], $row, $seat);
        $stmt->execute();
    }

    $stmt->close();

    header("Location: rezerwacje.php");
}


if ((isset($_POST['date']) && isset($_POST['time'])) || (isset($_COOKIE['date']) && isset($_COOKIE['time']))) {
    $stmt = $mysqli->prepare("SELECT `Rzad`, `Miejsce` FROM `rezerwacje` WHERE `Data` = ? AND `Godzina` = ?");
    if (isset($_POST['date']) && isset($_POST['time'])) {
        $stmt->bind_param('ss', $_POST['date'], $_POST['time']);
    } else {
        $stmt->bind_param('ss', $_COOKIE['date'], $_COOKIE['time']);
    }

    $stmt->execute();

    $stmt->bind_result($row, $seat);

    while ($stmt->fetch()) {
        $hall[$row - 1][$seat - 1] = 1;
    }

    $stmt->close();

    if (isset($_POST['date']) && isset($_POST['time'])) {
        setcookie("date", $_POST['date'], time() + 3600, "/");
        setcookie("time", $_POST['time'], time() + 3600, "/");
    }

    echo "<table>";
    for ($i = 0; $i < 15; $i++) {
        echo "<tr>";
        echo "<th>" . ($i + 1) . "</th>";
        for ($j = 0; $j < 20; $j++) {
            if ($reserved_seats && in_array($i * 20 + $j + 1, $reserved_seats)) {
                echo "<td class='yours'>" . ($j + 1) . "</td>";
                continue;
            }
            echo "<td class='" . ($hall[$i][$j] ? "occupied" : "free") . "' data-seat-id='" . ($i * 20 + $j + 1) . "'>" . ($j + 1) . "</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "Podaj poprawną datę i godzinę seansu";
}

$mysqli->close();
?>

<script>
    var seats = document.querySelectorAll('td');

    function updateCookie() {
        var selectedSeats = Array.from(document.querySelectorAll('.selected')).map(function(seat) {
            return seat.dataset.seatId;
        });
        document.cookie = "selectedSeats=" + JSON.stringify(selectedSeats);
    }

    seats.forEach(function(seat) {
        seat.addEventListener('click', function() {
            if (!this.classList.contains('occupied') && !this.classList.contains('yours')) {
                this.classList.toggle('selected');

                updateCookie();
            }
        });
    });

    window.addEventListener('load', function() {
        var cookie = document.cookie.split('; ').find(function(row) {
            return row.startsWith('selectedSeats=');
        });
        if (cookie) {
            var selectedSeats = JSON.parse(cookie.split('=')[1]);
            selectedSeats.forEach(function(seatId) {
                var seat = document.querySelector('td[data-seat-id="' + seatId + '"]');
                if (seat && !seat.classList.contains('occupied')) {
                    seat.classList.add('selected');
                }
            });
        }
    });
</script>
</body>
</html>