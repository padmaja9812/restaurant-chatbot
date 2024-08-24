<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "restaurant_chatbot";
$port = 3307;

date_default_timezone_set('Asia/Kolkata');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname, $port);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userMessage = $_POST['messageValue'];
    $response = "";

    try {
        if (preg_match('/\bhi\b|\bhello\b/i', $userMessage)) {
            $response = "Hello! How can I assist you today? You can ask me about our menu, specials, place an order, reserve a table, check our hours, or location.";
        } elseif (stripos($userMessage, 'menu') !== false) {
            $sql = "SELECT name, description, price, category FROM menuitems";
            $result = $conn->query($sql);

            if ($result && $result->num_rows > 0) {
                $response = "Here is our menu with descriptions:<br>";
                $vegItems = "<strong style='font-size: 1.5em; text-transform: uppercase;'>Veg Items:</strong><br>";
                $nonVegItems = "<strong style='font-size: 1.5em; text-transform: uppercase;'>Non-Veg Items:</strong><br>";
                while ($row = $result->fetch_assoc()) {
                    $item = $row['name'] . " - ₹" . $row['price'] . "<br>Description: " . $row['description'] . "<br><br>";
                    if ($row['category'] === 'Veg') {
                        $vegItems .= $item;
                    } else {
                        $nonVegItems .= $item;
                    }
                }
                $response .= $vegItems . "<br>" . $nonVegItems;
            } else {
                $response = "Our menu is currently unavailable.";
            }

        } elseif (stripos($userMessage, 'specials') !== false || stripos($userMessage, 'offers') !== false) {
            $sql = "SELECT special_details, description, price FROM specials WHERE date = CURDATE()";
            $result = $conn->query($sql);

            if ($result) {
                if ($result->num_rows > 0) {
                    $response = "Today's specials are:\n";
                    while ($row = $result->fetch_assoc()) {
                        $response .= $row['special_details'] . "\n";
                        $response .= "Description: " . $row['description'] . "\n";
                        $response .= "Price: " . $row['price'] . "\n\n";
                    }
                } else {
                    $response = "There are no specials today.";
                }
            } else {
                $response = "An error occurred while fetching specials: " . $conn->error;
            }
        } elseif (stripos($userMessage, 'order') !== false) {
            $orderDetails = substr($userMessage, stripos($userMessage, 'order') + 5);
            $orderDetails = trim($orderDetails);

            if (!empty($orderDetails)) {
                $items = explode(',', $orderDetails);
                $orderSummary = "Your order:\n";
                $totalPrice = 0;
                $itemFound = false;

                foreach ($items as $item) {
                    $item = trim($item);
                    $parts = preg_split('/\s+/', $item);
                    if (count($parts) >= 2) {
                        $quantity = (int) array_pop($parts);
                        $itemName = implode(' ', $parts);

                        if ($quantity > 0) {
                            $stmt = $conn->prepare("SELECT price FROM menuitems WHERE name = ?");
                            $stmt->bind_param('s', $itemName);
                            $stmt->execute();
                            $result = $stmt->get_result();

                            if ($result->num_rows > 0) {
                                $row = $result->fetch_assoc();
                                $itemPrice = $row['price'];
                                $totalPrice += $itemPrice * $quantity;

                                $stmt = $conn->prepare("INSERT INTO orders (item_name, quantity) VALUES (?, ?)");
                                $stmt->bind_param('si', $itemName, $quantity);
                                $stmt->execute();

                                $orderSummary .= "$itemName x $quantity (₹$itemPrice each)<br>";
                                $itemFound = true;
                            } else {
                                // Check if the item is a special item
                                $stmt = $conn->prepare("SELECT price FROM specials WHERE special_details = ?");
                                $stmt->bind_param('s', $itemName);
                                $stmt->execute();
                                $result = $stmt->get_result();

                                if ($result->num_rows > 0) {
                                    $row = $result->fetch_assoc();
                                    $itemPrice = $row['price'];
                                    $totalPrice += $itemPrice * $quantity;

                                    // Perform the order for special items
                                    $stmt = $conn->prepare("INSERT INTO orders (item_name, quantity) VALUES (?, ?)");
                                    $stmt->bind_param('si', $itemName, $quantity);
                                    $stmt->execute();

                                    $orderSummary .= "$itemName x $quantity (₹$itemPrice each)<br>";
                                    $itemFound = true;
                                } else {
                                    $orderSummary .= "$itemName is not on the menu or price is not available.\n";
                                }
                            }
                        } else {
                            $orderSummary .= "Invalid quantity for $itemName.\n";
                        }
                    } else {
                        $orderSummary .= "Invalid format for $item.\n";
                    }
                }

                if ($itemFound) {
                    $response = $orderSummary . "\nTotal Price: ₹" . number_format($totalPrice, 2);
                } else {
                    $response = $orderSummary;
                }
            } else {
                $response = "Please specify the items you would like to order, in the format 'order item_name quantity, item_name quantity, ...'.";
            }

        } elseif (stripos($userMessage, 'reserve') !== false) {
            $reservationDetails = substr($userMessage, stripos($userMessage, 'reserve') + 7);
            $reservationDetails = trim($reservationDetails);
            $parts = explode(',', $reservationDetails);

            if (count($parts) === 3) {
                // Extract and clean up the date, time, and number of people
                $date = trim($parts[0]);
                $time = trim($parts[1]);
                $numPeople = (int) trim($parts[2]);

                // Convert date to 'Y-m-d' format
                $date = date('Y-m-d', strtotime($date));

                // Convert time to 24-hour format for database storage
                $time24Format = date('H:i:s', strtotime($time));

                if (strtotime($date) && strtotime($time24Format) && $numPeople > 0) {
                    $currentDateTime = date("Y-m-d H:i:s");

                    $stmt = $conn->prepare("INSERT INTO reservations (reservation_date, reservation_time, num_people, reservation_made) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('ssis', $date, $time24Format, $numPeople, $currentDateTime);
                    $stmt->execute();

                    $time12Format = date('h:i A', strtotime($time24Format)); // Convert to 12-hour format for display
                    $response = "Reservation confirmed for $date at $time12Format for $numPeople people.";
                } else {
                    $response = "Please provide a valid date, time, and number of people for the reservation.";
                }
            } else {
                $response = "Please provide the reservation details in the format 'reserve date, time, number of people'.";
            }


        } elseif (stripos($userMessage, 'hours') !== false || stripos($userMessage, 'timings') !== false) {
            $response = "We are open from 8 AM to 11 PM, Monday to Sunday.";

        } elseif (stripos($userMessage, 'location') !== false) {
            $response = "We are located in Hyderabad.";

        } elseif (stripos($userMessage, 'feedback') !== false && preg_match('/rating\s+(\d+)/i', $userMessage, $matches)) {
            $rating = $matches[1];
            $feedback = str_ireplace('feedback', '', $userMessage); // Extract feedback
            $feedback = str_ireplace('rating ' . $rating, '', $feedback); // Remove rating from feedback

            // Trim excess spaces
            $feedback = trim($feedback);
            $rating = trim($rating);

            $response = "Thank you for your feedback!\n";
            $response .= "Rating: $rating\n";
            $response .= "Comments: $feedback";
        } else {

            // Handle specific menu item queries
            $item = trim($userMessage);
            $sql = "SELECT name, price, description FROM menuitems WHERE name LIKE ?";
            $stmt = $conn->prepare($sql);
            $likeItem = "%" . $item . "%";
            $stmt->bind_param('s', $likeItem);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $response = "Item details:\n";
                while ($row = $result->fetch_assoc()) {
                    $response .= $row['name'] . " - ₹" . $row['price'] . "\nDescription: " . $row['description'] . "\n";
                }
            } else {
                $response = "I'm not sure how to help with that. Could you please provide more details or try asking another question?";
            }
        }
    } catch (Exception $e) {
        $response = "An error occurred: " . $e->getMessage();
    }
    echo nl2br($response);
}

$conn->close();
?>