<?php
require_once __DIR__ . '/../includes/db_connect.php';
require_once __DIR__ . '/../includes/auth.php';

requireLogin();

$screeningId = isset($_GET['screening_id']) ? (int)$_GET['screening_id'] : 1;

// Fetch Screening and Movie details
$sql = "
    SELECT s.screening_id, s.screening_date, s.screening_time,
           m.movie_id, m.title AS movie_title, m.rating, m.duration_minutes, m.genre, m.poster_image,
           h.hall_id, h.hall_name, h.experience_type, h.total_rows, h.seats_per_row, 
           h.premium_row_start, h.standard_price, h.premium_price
    FROM screenings s
    JOIN movies m ON s.movie_id = m.movie_id
    JOIN halls h ON s.hall_id = h.hall_id
    WHERE s.screening_id = ?
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $screeningId);
$stmt->execute();
$screening = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$screening) {
    header("Location: ../movies.php");
    exit;
}

// Fetch all booked seats
$bStmt = $conn->prepare("
    SELECT bs.seat_label 
    FROM booked_seats bs 
    JOIN bookings b ON bs.booking_id = b.booking_id 
    WHERE b.screening_id = ? AND b.status = 'confirmed'
");
$bStmt->bind_param("i", $screeningId);
$bStmt->execute();
$bRes = $bStmt->get_result();
$bookedSeats = [];
while ($row = $bRes->fetch_assoc()) {
    $bookedSeats[] = $row['seat_label'];
}
$bStmt->close();

// Prepare seat matrix JSON for Vue.js reactivity
$totalRows = (int)$screening['total_rows'];
$seatsPerRow = (int)$screening['seats_per_row'];
$premiumRowStart = (int)$screening['premium_row_start'];

$seatRows = [];
for ($r = 1; $r <= $totalRows; $r++) {
    $rowLetter = chr(64 + $r);
    $isPrem = ($r >= $premiumRowStart);
    $seats = [];
    for ($c = 1; $c <= $seatsPerRow; $c++) {
        $label = $rowLetter . $c;
        $seats[] = [
            'label' => $label,
            'col' => $c,
            'isPremium' => $isPrem,
            'price' => $isPrem ? (float)$screening['premium_price'] : (float)$screening['standard_price'],
            'isBooked' => in_array($label, $bookedSeats)
        ];
    }
    $seatRows[] = [
        'rowLetter' => $rowLetter,
        'isPremium' => $isPrem,
        'seats' => $seats
    ];
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enhanced Vue.js Seat Selection - Silver Village Cinema</title>
    <!-- Modern Enhancement 1: Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        gold: {
                            400: '#f2ca50',
                            500: '#d4af37',
                            600: '#b89326',
                        },
                        midnight: {
                            900: '#0a0e17',
                            800: '#0f131c',
                            700: '#181b25',
                            600: '#262a34',
                        }
                    },
                    fontFamily: {
                        serif: ['Playfair Display', 'Georgia', 'serif'],
                        sans: ['Inter', 'system-ui', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- Modern Enhancement 2: Vue.js 3 CDN -->
    <script src="https://unpkg.com/vue@3/dist/vue.global.prod.js"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:ital,wght@0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        h1, h2, h3 { font-family: 'Playfair Display', serif; }
    </style>
</head>
<body class="bg-midnight-900 text-slate-200 min-h-screen flex flex-col antialiased">
    <!-- Header -->
    <header class="sticky top-0 z-50 bg-midnight-900/90 backdrop-blur-md border-b border-slate-800 px-6 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <a href="../index.php" class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-br from-gold-500 to-amber-700 text-midnight-900 rounded flex items-center justify-center font-bold text-lg shadow-lg shadow-gold-500/20">
                    SV
                </div>
                <div>
                    <span class="font-serif font-bold text-gold-400 tracking-wider text-lg block leading-none">SILVER VILLAGE</span>
                    <span class="text-[10px] tracking-widest text-slate-400 uppercase font-semibold">Vue.js Enhanced Version</span>
                </div>
            </a>

            <div class="flex items-center gap-4">
                <span class="px-3 py-1 text-xs font-semibold rounded-full bg-indigo-500/20 text-indigo-300 border border-indigo-500/40">
                    ⚡ Vue 3 Reactive App
                </span>
                <a href="../booking.php?screening_id=<?php echo $screeningId; ?>" class="text-xs text-slate-400 hover:text-gold-400 transition">
                    &larr; Switch to Standard Version
                </a>
            </div>
        </div>
    </header>

    <!-- Vue App Mounting Root -->
    <div id="app" class="flex-1 max-w-7xl w-full mx-auto px-6 py-8">
        <!-- Top Info Header -->
        <div class="mb-8 flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 pb-6">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="bg-rose-600 text-white text-xs font-bold px-2 py-0.5 rounded">
                        <?php echo htmlspecialchars($screening['rating']); ?>
                    </span>
                    <span class="text-xs text-gold-400 font-medium tracking-wide">
                        <?php echo htmlspecialchars($screening['experience_type']); ?>
                    </span>
                </div>
                <h1 class="text-3xl lg:text-4xl text-white font-bold tracking-tight">
                    <?php echo htmlspecialchars($screening['movie_title']); ?>
                </h1>
                <p class="text-slate-400 text-sm mt-1">
                    🎭 <?php echo htmlspecialchars($screening['hall_name']); ?> • 
                    📅 <?php echo date('D, d M Y', strtotime($screening['screening_date'])); ?> • 
                    ⏰ <?php echo date('h:i A', strtotime($screening['screening_time'])); ?>
                </p>
            </div>

            <!-- Live Status Counter Pill -->
            <div class="bg-midnight-800 border border-slate-800 rounded-xl p-4 flex items-center gap-6">
                <div>
                    <span class="text-xs text-slate-400 block uppercase font-medium">Selected Seats</span>
                    <span class="text-2xl font-bold text-gold-400">{{ selectedSeats.length }}</span>
                </div>
                <div class="h-8 w-px bg-slate-800"></div>
                <div>
                    <span class="text-xs text-slate-400 block uppercase font-medium">Running Total</span>
                    <span class="text-2xl font-bold font-serif text-white">\${{ grandTotal.toFixed(2) }}</span>
                </div>
            </div>
        </div>

        <!-- Main Booking Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
            <!-- Left 8 Cols: Interactive Seat Visualizer -->
            <div class="lg:col-span-8 bg-midnight-800/80 border border-slate-800 rounded-2xl p-6 lg:p-8 backdrop-blur shadow-2xl">
                <!-- Curved Cinema Screen -->
                <div class="relative w-4/5 mx-auto mb-10 text-center">
                    <div class="h-4 border-t-4 border-gold-400 rounded-t-full shadow-[0_-8px_24px_rgba(212,175,55,0.35)]"></div>
                    <span class="text-[10px] tracking-[0.3em] font-bold text-slate-400 uppercase mt-2 block">
                        CURVED CINEMA LASER SCREEN
                    </span>
                </div>

                <!-- Seat Grid -->
                <div class="flex flex-col items-center gap-3 overflow-x-auto py-4">
                    <div v-for="row in seatRows" :key="row.rowLetter" class="flex items-center gap-2">
                        <span class="w-6 text-center text-xs font-bold text-slate-400">{{ row.rowLetter }}</span>
                        
                        <button v-for="seat in row.seats" :key="seat.label"
                                @click="toggleSeat(seat)"
                                :disabled="seat.isBooked"
                                :class="[
                                    'w-8 h-8 text-xs font-semibold rounded-t-md rounded-b-sm transition-all duration-150 flex items-center justify-center select-none',
                                    seat.isBooked ? 'bg-slate-900 border border-slate-800 text-slate-700 cursor-not-allowed line-through' :
                                    isSeatSelected(seat.label) ? 'bg-gold-400 border-gold-400 text-midnight-900 font-bold scale-110 shadow-lg shadow-gold-400/40' :
                                    seat.isPremium ? 'bg-amber-950/40 border border-gold-500/60 text-gold-300 hover:border-gold-400 hover:scale-105' :
                                    'bg-slate-800 border border-slate-700 text-slate-300 hover:border-slate-500 hover:scale-105'
                                ]"
                                :title="seat.label + ' - ' + (seat.isPremium ? 'Premium Recliner' : 'Standard') + ' ($' + seat.price.toFixed(2) + ')'">
                            {{ seat.col }}
                        </button>

                        <span class="w-6 text-center text-xs font-bold text-slate-400">{{ row.rowLetter }}</span>
                    </div>
                </div>

                <!-- Legend Bar -->
                <div class="flex flex-wrap items-center justify-center gap-6 pt-6 mt-6 border-t border-slate-800 text-xs">
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded-sm bg-slate-800 border border-slate-700 inline-block"></span>
                        <span>Standard (\$<?php echo number_format($screening['standard_price'], 2); ?>)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded-sm bg-amber-950/40 border border-gold-500/60 inline-block"></span>
                        <span>Premium Recliner (\$<?php echo number_format($screening['premium_price'], 2); ?>)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded-sm bg-gold-400 inline-block shadow-sm"></span>
                        <span>Selected</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="w-4 h-4 rounded-sm bg-slate-900 border border-slate-800 inline-block line-through text-slate-700"></span>
                        <span>Occupied</span>
                    </div>
                </div>
            </div>

            <!-- Right 4 Cols: Reactive Wishlist & Checkout Sidebar -->
            <div class="lg:col-span-4 bg-midnight-800 border border-slate-800 rounded-2xl p-6 shadow-xl sticky top-24">
                <h2 class="text-xl font-bold text-gold-400 mb-4 border-b border-slate-800 pb-3 font-serif">
                    Booking Summary
                </h2>

                <!-- Selected Seats Tags -->
                <div class="mb-6">
                    <span class="text-xs text-slate-400 block mb-2 font-medium">Selected Seat Allocations</span>
                    <div v-if="selectedSeats.length > 0" class="flex flex-wrap gap-2">
                        <span v-for="s in selectedSeats" :key="s.label"
                              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-bold bg-midnight-700 text-gold-300 border border-gold-500/30">
                            {{ s.label }} ({{ s.isPremium ? 'Prem' : 'Std' }})
                            <button @click="removeSeat(s.label)" class="text-slate-400 hover:text-rose-400 font-normal ml-0.5">&times;</button>
                        </span>
                    </div>
                    <p v-else class="text-xs text-slate-500 italic py-2">
                        No seats selected yet. Click any available seat on the hall map.
                    </p>
                </div>

                <!-- Price Calculation Breakdown -->
                <div class="bg-midnight-900/90 rounded-xl p-4 mb-6 text-sm space-y-2 border border-slate-800/80">
                    <div class="flex justify-between text-slate-400 text-xs">
                        <span>Standard Tickets:</span>
                        <span class="text-slate-200">{{ standardCount }} &times; \$<?php echo number_format($screening['standard_price'], 2); ?></span>
                    </div>
                    <div class="flex justify-between text-slate-400 text-xs">
                        <span>Premium Recliners:</span>
                        <span class="text-slate-200">{{ premiumCount }} &times; \$<?php echo number_format($screening['premium_price'], 2); ?></span>
                    </div>
                    <div class="flex justify-between text-slate-400 text-xs">
                        <span>Hall Experience:</span>
                        <span class="text-slate-200">Included</span>
                    </div>
                    <div class="pt-2 border-t border-slate-800 flex justify-between items-center text-base">
                        <span class="font-bold text-white">Total Payable:</span>
                        <span class="font-serif font-bold text-xl text-gold-400">\${{ grandTotal.toFixed(2) }}</span>
                    </div>
                </div>

                <!-- Preference Rank Selector (Course Requirement Fulfillment) -->
                <form method="POST" action="../booking.php?screening_id=<?php echo $screeningId; ?>" @submit="validateSubmit">
                    <input type="hidden" name="selected_seats" :value="selectedSeatsLabels">

                    <div class="mb-5">
                        <label class="block text-xs font-semibold text-slate-300 mb-1.5">
                            Preference Rank <span class="text-gold-400 font-normal">(For Shortlist Wishlist)</span>
                        </label>
                        <select name="preference_rank" v-model="preferenceRank" class="w-full bg-midnight-900 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-gold-400">
                            <option value="1">Preference #1 (Top Choice)</option>
                            <option value="2">Preference #2 (Alternative Option)</option>
                            <option value="3">Preference #3 (Backup Option)</option>
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1">
                            Save multiple showtime options to your account and review availability together before checkout.
                        </p>
                    </div>

                    <button type="submit" name="booking_action" value="add_wishlist"
                            :disabled="selectedSeats.length === 0"
                            :class="[
                                'w-full py-2.5 px-4 mb-2.5 rounded-lg border text-sm font-semibold transition flex items-center justify-center gap-2',
                                selectedSeats.length === 0 ? 'border-slate-800 text-slate-600 cursor-not-allowed' : 'border-gold-500/50 text-gold-300 hover:bg-gold-500/10 hover:border-gold-400'
                            ]">
                        📋 Add to Booking Wishlist
                    </button>

                    <button type="submit" name="booking_action" value="direct_checkout"
                            :disabled="selectedSeats.length === 0"
                            :class="[
                                'w-full py-3 px-4 rounded-lg text-sm font-bold transition shadow-lg',
                                selectedSeats.length === 0 ? 'bg-slate-800 text-slate-600 cursor-not-allowed' : 'bg-gold-500 text-midnight-900 hover:bg-gold-400 shadow-gold-500/20 hover:scale-[1.02]'
                            ]">
                        Proceed to Checkout &rarr;
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Vue 3 Reactivity Controller -->
    <script>
        const { createApp } = Vue;

        createApp({
            data() {
                return {
                    seatRows: <?php echo json_encode($seatRows); ?>,
                    selectedSeats: [],
                    preferenceRank: '1'
                }
            },
            computed: {
                selectedSeatsLabels() {
                    return this.selectedSeats.map(s => s.label).join(',');
                },
                standardCount() {
                    return this.selectedSeats.filter(s => !s.isPremium).length;
                },
                premiumCount() {
                    return this.selectedSeats.filter(s => s.isPremium).length;
                },
                grandTotal() {
                    return this.selectedSeats.reduce((sum, s) => sum + s.price, 0);
                }
            },
            methods: {
                isSeatSelected(label) {
                    return this.selectedSeats.some(s => s.label === label);
                },
                toggleSeat(seat) {
                    if (seat.isBooked) return;
                    const idx = this.selectedSeats.findIndex(s => s.label === seat.label);
                    if (idx > -1) {
                        this.selectedSeats.splice(idx, 1);
                    } else {
                        this.selectedSeats.push({
                            label: seat.label,
                            isPremium: seat.isPremium,
                            price: seat.price
                        });
                    }
                },
                removeSeat(label) {
                    const idx = this.selectedSeats.findIndex(s => s.label === label);
                    if (idx > -1) {
                        this.selectedSeats.splice(idx, 1);
                    }
                },
                validateSubmit(e) {
                    if (this.selectedSeats.length === 0) {
                        e.preventDefault();
                        alert('Please select at least one seat before proceeding.');
                    }
                }
            }
        }).mount('#app');
    </script>
</body>
</html>
