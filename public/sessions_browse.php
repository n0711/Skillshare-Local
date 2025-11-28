<?php
require_once "../config/db.php";
require_once "../includes/auth.php";
require_once "../includes/header.php";

/*
 * List all upcoming sessions with filters:
 * - keyword (title)
 * - category
 * - location (city or "online")
 * - fee (free / paid)
 * Also shows:
 * - photo
 * - average rating
 * - capacity info and "Book now" vs "Full"
 */

// categories for filter dropdown
$catResult = $mysqli->query("SELECT id, name FROM categories ORDER BY name");

// main query with average rating + bookings count
$sql = "
    SELECT 
        s.id,
        s.title,
        s.description,
        s.mode,
        s.location_city,
        s.fee_type,
        s.fee_amount,
        s.start_datetime,
        s.capacity,
        s.photo_path,
        c.name AS category_name,
        u.name AS instructor_name,
        ROUND(AVG(r.rating),1) AS avg_rating,
        COUNT(r.id) AS rating_count,
        SUM(CASE WHEN b.status IN ('pending','accepted') THEN 1 ELSE 0 END) AS current_bookings
    FROM sessions s
    JOIN categories c ON s.category_id = c.id
    JOIN users u ON s.instructor_id = u.id
    LEFT JOIN bookings b ON b.session_id = s.id
    LEFT JOIN ratings  r ON r.booking_id = b.id
    WHERE s.status = 'active'
      AND s.start_datetime >= NOW()
    GROUP BY
        s.id, s.title, s.description, s.mode, s.location_city,
        s.fee_type, s.fee_amount, s.start_datetime,
        s.capacity, s.photo_path,
        c.name, u.name
    ORDER BY s.start_datetime ASC
";

$result = $mysqli->query($sql);
?>

<h1>Find sessions</h1>

<div class="row mb-3">
  <div class="col-md-3 mb-2">
    <input id="searchTitle" class="form-control" placeholder="Search by title...">
  </div>
  <div class="col-md-3 mb-2">
    <select id="filterCategory" class="form-select">
      <option value="">All categories</option>
      <?php while ($cat = $catResult->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($cat["name"]) ?>">
          <?= htmlspecialchars($cat["name"]) ?>
        </option>
      <?php endwhile; ?>
    </select>
  </div>
  <div class="col-md-3 mb-2">
    <input id="filterCity" class="form-control" placeholder="City or 'online'">
  </div>
  <div class="col-md-3 mb-2">
    <select id="filterFee" class="form-select">
      <option value="">Free & paid</option>
      <option value="free">Free only</option>
      <option value="paid">Paid only</option>
    </select>
  </div>
</div>

<?php if ($result->num_rows === 0): ?>
  <div class="alert alert-info">No upcoming sessions yet.</div>
<?php endif; ?>

<div id="sessionList">
<?php while ($row = $result->fetch_assoc()): ?>
  <?php
    $city       = $row["location_city"] ?: "";
    $isFree     = ($row["fee_type"] === "free");
    $booked     = (int)$row["current_bookings"];
    $capacity   = (int)$row["capacity"];
    $isFull     = ($capacity > 0 && $booked >= $capacity);
    $cityLabel  = $row["mode"] === "online"
                    ? "Online"
                    : ($city !== "" ? $city : "In-person");
  ?>
  <div class="card mb-3 card-session"
       data-title="<?= strtolower(htmlspecialchars($row["title"])) ?>"
       data-category="<?= htmlspecialchars($row["category_name"]) ?>"
       data-mode="<?= htmlspecialchars($row["mode"]) ?>"
       data-city="<?= strtolower(htmlspecialchars($cityLabel)) ?>"
       data-fee="<?= htmlspecialchars($row["fee_type"]) ?>">
    <div class="row g-0">
      <div class="col-md-3 d-flex align-items-stretch">
        <?php if (!empty($row["photo_path"])): ?>
          <img src="/skillshare_local/<?= htmlspecialchars($row["photo_path"]) ?>"
               alt="Session photo"
               class="img-fluid rounded-start"
               style="width: 100%; height: 100%; object-fit: cover;">
        <?php else: ?>
          <div class="d-flex w-100 h-100 justify-content-center align-items-center bg-light text-muted"
               style="min-height: 120px;">
            No photo
          </div>
        <?php endif; ?>
      </div>
      <div class="col-md-9">
        <div class="card-body">
          <h5 class="card-title"><?= htmlspecialchars($row["title"]) ?></h5>

          <p class="card-text small text-muted mb-1">
            <?= htmlspecialchars($row["category_name"]) ?> •
            <?= htmlspecialchars($row["mode"]) ?> •
            <?= htmlspecialchars($cityLabel) ?> •
            with <?= htmlspecialchars($row["instructor_name"]) ?>
          </p>

          <?php if ($row["rating_count"] > 0): ?>
            <p class="card-text small mb-1">
              ⭐ <?= number_format($row["avg_rating"], 1) ?>/5
              (<?= (int)$row["rating_count"] ?> rating<?= $row["rating_count"] > 1 ? "s" : "" ?>)
            </p>
          <?php else: ?>
            <p class="card-text small text-muted mb-1">Not rated yet.</p>
          <?php endif; ?>

          <p class="card-text">
            <?= nl2br(htmlspecialchars(mb_strimwidth($row["description"], 0, 160, "..."))) ?>
          </p>

          <p class="card-text mb-2">
            <span class="badge bg-secondary">
                <?= date("d M Y H:i", strtotime($row["start_datetime"])) ?>
            </span>
            <?php if ($isFree): ?>
                <span class="badge bg-success">Free</span>
            <?php else: ?>
                <span class="badge bg-primary">
                  <?= number_format($row["fee_amount"], 2) ?> €
                </span>
            <?php endif; ?>
            <span class="badge bg-dark">
              <?= $booked ?> / <?= $capacity ?> booked
            </span>
            <?php if ($isFull): ?>
              <span class="badge bg-danger">Full</span>
            <?php endif; ?>
          </p>

          <a href="session_view.php?id=<?= $row["id"] ?>"
             class="btn btn-sm <?= $isFull ? 'btn-outline-secondary disabled' : 'btn-outline-success' ?>">
            <?= $isFull ? 'Full' : 'Book now' ?>
          </a>
        </div>
      </div>
    </div>
  </div>
<?php endwhile; ?>
</div>

<p class="small text-muted mb-2" id="sessionCountInfo">
  Showing <span id="sessionCount">0</span> sessions.
</p>
<script src="/skillshare_local/assets/js/browse_sessions.js"></script>
<?php require_once "../includes/footer.php"; ?>
