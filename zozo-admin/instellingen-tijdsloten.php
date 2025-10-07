<!-- Tijdsloten: unified add / list -->
<div id="tab-tijdsloten" class="tab-content <?= isset($active_tab) && $active_tab === 'tijdsloten' ? '' : 'hidden' ?>">
    <!-- inner-tab styles moved to zozo-admin/css/main.css -->

    <div class="inner-tabs" style="margin-top:6px;">
        <?php
        // Simple server-side visibility flags for the inner subtabs.
        // Prefer explicit GET params if present, otherwise fall back to $active_sub
        $req_tab = isset($_GET['tab']) ? $_GET['tab'] : null;
        $req_sub = isset($_GET['sub']) ? $_GET['sub'] : null;

        $show_ts_main = false;
        $show_ts_days = false;
        $show_ts_holiday = false;

        if ($req_tab === 'tijdsloten' && $req_sub === 'ts-main') $show_ts_main = true;
        if ($req_tab === 'tijdsloten' && $req_sub === 'ts-days') $show_ts_days = true;
        if ($req_tab === 'tijdsloten' && $req_sub === 'ts-holiday') $show_ts_holiday = true;

        // fallback to controller-resolved $active_sub when GET not present
        if ($req_tab === null && isset($active_sub)) {
            if ($active_sub === 'ts-main') $show_ts_main = true;
            if ($active_sub === 'ts-days') $show_ts_days = true;
            if ($active_sub === 'ts-holiday') $show_ts_holiday = true;
        }

        // default: show main if none selected
        if (!$show_ts_main && !$show_ts_days && !$show_ts_holiday) $show_ts_main = true;
        ?>
        <div class="inner-tab-buttons" style="margin-bottom:12px;">
            <a class="inner-tab-btn <?= $active_sub === 'ts-main' ? 'active' : '' ?>" href="/admin/instellingen/tijdsloten-instellen?tab=tijdsloten&sub=ts-main" style="margin-right:8px;padding:6px 10px;border-radius:4px;border:1px solid #ddd;background:#fff;cursor:pointer;">Tijdsloten instellen</a>
            <a class="inner-tab-btn <?= $active_sub === 'ts-days' ? 'active' : '' ?>" href="/admin/instellingen/tijdsloten-aantal-dagen?tab=tijdsloten&sub=ts-days" style="margin-right:8px;padding:6px 10px;border-radius:4px;border:1px solid #ddd;background:#fff;cursor:pointer;">Aantal dagen</a>
            <a class="inner-tab-btn <?= $active_sub === 'ts-holiday' ? 'active' : '' ?>" href="/admin/instellingen/tijdsloten-vakantie?tab=tijdsloten&sub=ts-holiday" style="padding:6px 10px;border-radius:4px;border:1px solid #ddd;background:#fff;cursor:pointer;">Vakantie</a>
        </div>

        <div id="ts-main" class="inner-tab-panel <?= $show_ts_main ? '' : 'hidden' ?>">
            <div class="settings-card">
                <div style="display:flex;justify-content:flex-end;margin-bottom:0.5rem;">
                    <button id="open_add_timeslot" class="btn btn--add">Voeg tijdslot toe</button>
                </div>

                <!-- Add timeslot modal (hidden by default) -->
                <div id="addTimeslotModal" class="modal" style="display:none;position:fixed;inset:0;z-index:1200;align-items:center;justify-content:center;">
                    <div class="modal-backdrop" id="modalBackdrop" style="position:absolute;inset:0;background:rgba(0,0,0,0.4);"></div>
                    <div class="modal-content" role="dialog" aria-modal="true" style="background:#fff;padding:18px;border-radius:6px;min-width:320px;max-width:520px;z-index:1210;box-shadow:0 8px 24px rgba(0,0,0,0.2);">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <h3 style="margin:0;">Voeg tijdslot toe</h3>
                            <button id="close_add_timeslot" aria-label="Sluit" style="background:transparent;border:none;font-size:20px;cursor:pointer;">&times;</button>
                        </div>
                        <form method="post" action="/admin/instellingen" id="modal_add_form">
                            <input type="hidden" name="add_timeslot" value="1">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <div>
                                    <label class="form-label">Type</label>
                                    <select name="type" class="form-input">
                                        <option value="pickup">Afhaling</option>
                                        <option value="delivery">Levering</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Dag</label>
                                    <select name="day" class="form-input">
                                        <option value="1">Maandag</option>
                                        <option value="2">Dinsdag</option>
                                        <option value="3">Woensdag</option>
                                        <option value="4">Donderdag</option>
                                        <option value="5">Vrijdag</option>
                                        <option value="6">Zaterdag</option>
                                        <option value="7">Zondag</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="form-label">Begintijd</label>
                                    <input type="time" name="start_time" class="form-input" required>
                                </div>
                                <div>
                                    <label class="form-label">Eindtijd</label>
                                    <input type="time" name="end_time" class="form-input" required>
                                </div>
                                <div>
                                    <label class="form-label">Capaciteit</label>
                                    <input type="number" name="capacity" class="form-input" min="1">
                                </div>
                                <div>
                                    <label class="form-label">Voorbereiding (min)</label>
                                    <input type="number" name="preparation_minutes" class="form-input" min="0" placeholder="0">
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;">
                                    <!-- 'active' column removed from schema; no checkbox needed -->
                                </div>
                            </div>
                            <div style="margin-top:12px;text-align:right;">
                                <button type="button" id="modal_cancel" class="btn">Annuleer</button>
                                <button type="submit" class="btn btn--add">Voeg tijdslot toe</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div style="margin-top:12px;">
                    <?php
                    // Render list grouped by day and type into day-cards
                    $dagenNaam = [1 => 'Maandag', 2 => 'Dinsdag', 3 => 'Woensdag', 4 => 'Donderdag', 5 => 'Vrijdag', 6 => 'Zaterdag', 7 => 'Zondag'];
                    foreach ($dagenNaam as $di => $dnaam):
                    ?>
                        <div class="settings-card" style="margin-bottom:14px;">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                                <h4 style="margin:0;font-size:1.1rem;color:#0b3d91;"><?= $dnaam ?></h4>
                            </div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                                <div class="timeslot-subcard">
                                    <strong style="display:block;margin-bottom:8px;color:#1e293b;">Afhaling</strong>
                                    <?php $p = $timeslot_config['pickup'][$di] ?? null; ?>
                                    <?php $pranges = $p['ranges'] ?? []; ?>
                                    <?php if (!empty($pranges)): ?>
                                        <table class="table" style="margin-top:8px;width:100%;text-align:center;">
                                            <thead>
                                                <tr>
                                                    <th style="text-align:left;width:30%">Van</th>
                                                    <th style="text-align:left;width:30%">Tot</th>
                                                    <th style="text-align:center;width:15%">Aantal</th>
                                                    <th style="text-align:center;width:15%">Voorb. (min)</th>
                                                    <th style="text-align:center;width:10%"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pranges as $pr): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars(fmt_time($pr['start_time'])) ?></td>
                                                        <td><?= htmlspecialchars(fmt_time($pr['end_time'])) ?></td>
                                                        <td style="text-align:center;"><?= htmlspecialchars($pr['capacity'] ?? '-') ?></td>
                                                        <td style="text-align:center;"><?= htmlspecialchars($pr['preparation_minutes'] !== null ? $pr['preparation_minutes'] : '-') ?></td>
                                                        <td style="text-align:center;">
                                                            <button class="ajax-delete" data-id="<?= (int)$pr['id'] ?>" title="Verwijder" style="background:transparent;border:none;color:#c0392b;font-weight:bold;font-size:18px;line-height:1;cursor:pointer;">&times;</button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="muted">Geen afhaaltijd ingesteld</div>
                                    <?php endif; ?>
                                </div>

                                <div class="timeslot-subcard">
                                    <strong style="display:block;margin-bottom:8px;color:#1e293b;">Levering</strong>
                                    <?php $dlist = $timeslot_config['delivery'][$di]['ranges'] ?? []; ?>
                                    <?php if (!empty($dlist)): ?>
                                        <table class="table" style="width:100%;text-align:center;">
                                            <thead>
                                                <tr>
                                                    <th style="text-align:left;width:30%">Van</th>
                                                    <th style="text-align:left;width:30%">Tot</th>
                                                    <th style="text-align:center;width:15%">Aantal</th>
                                                    <th style="text-align:center;width:15%">Voorb. (min)</th>
                                                    <th style="text-align:center;width:10%"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($dlist as $r): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars(fmt_time($r['start_time'])) ?></td>
                                                        <td><?= htmlspecialchars(fmt_time($r['end_time'])) ?></td>
                                                        <td style="text-align:center;"><?= htmlspecialchars($r['capacity'] ?? '-') ?></td>
                                                        <td style="text-align:center;"><?= htmlspecialchars($r['preparation_minutes'] !== null ? $r['preparation_minutes'] : '-') ?></td>
                                                        <td style="text-align:center;">
                                                            <button class="ajax-delete" data-id="<?= (int)$r['id'] ?>" title="Verwijder" style="background:transparent;border:none;color:#c0392b;font-weight:bold;font-size:18px;line-height:1;cursor:pointer;">&times;</button>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    <?php else: ?>
                                        <div class="muted">Geen leveringsblokken ingesteld</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div id="ts-days" class="inner-tab-panel <?= $show_ts_days ? '' : 'hidden' ?>" style="padding:12px;">
            <div class="settings-card">
                <form method="post" action="/admin/instellingen/tijdsloten-aantal-dagen?tab=tijdsloten&sub=ts-days" class="form-section">
                    <div class="form-section-box">
                        <h3 class="form-section-title">Aantal dagen in checkout</h3>
                        <div>
                            <label class="form-label">Aantal dagen tonen in checkout (vanaf vandaag)</label>
                            <input type="number" name="tijdsloten_dagen" min="1" max="365" class="form-input" value="<?= isset($instellingen['tijdsloten_dagen']) ? intval($instellingen['tijdsloten_dagen']) : 14 ?>">
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="save_tijdsloten_days" class="btn btn--add">Opslaan</button>
                    </div>
                </form>
            </div>
        </div>

        <div id="ts-holiday" class="inner-tab-panel <?= $show_ts_holiday ? '' : 'hidden' ?>" style="padding:12px;background:#fafafa;border:1px solid #f0f0f0;border-radius:6px;">
            <div class="settings-card">
                <div style="display:flex;justify-content:flex-end;margin-bottom:0.5rem;">
                    <button id="open_add_holiday" class="btn btn--add">Vakantie toevoegen</button>
                </div>

                <!-- Add holiday modal (hidden by default) -->
                <div id="addHolidayModal" class="modal" style="display:none;position:fixed;inset:0;z-index:1200;align-items:center;justify-content:center;">
                    <div class="modal-backdrop" id="holidayModalBackdrop" style="position:absolute;inset:0;background:rgba(0,0,0,0.4);"></div>
                    <div class="modal-content" role="dialog" aria-modal="true" style="background:#fff;padding:18px;border-radius:6px;min-width:320px;max-width:520px;z-index:1210;box-shadow:0 8px 24px rgba(0,0,0,0.2);">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                            <h3 style="margin:0;">Vakantie toevoegen</h3>
                            <button id="close_add_holiday" aria-label="Sluit" style="background:transparent;border:none;font-size:20px;cursor:pointer;">&times;</button>
                        </div>
                        <form method="post" action="/admin/instellingen" id="modal_add_holiday_form">
                            <input type="hidden" name="add_holiday" value="1">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                                <div>
                                    <label class="form-label">Start datum</label>
                                    <input type="date" name="start_date" class="form-input" required>
                                </div>
                                <div>
                                    <label class="form-label">Start tijd</label>
                                    <input type="time" name="start_time" class="form-input" required>
                                </div>
                                <div>
                                    <label class="form-label">Eind datum</label>
                                    <input type="date" name="end_date" class="form-input" required>
                                </div>
                                <div>
                                    <label class="form-label">Eind tijd</label>
                                    <input type="time" name="end_time" class="form-input" required>
                                </div>
                            </div>
                            <div style="margin-top:12px;text-align:right;">
                                <button type="button" id="holiday_modal_cancel" class="btn">Annuleer</button>
                                <button type="submit" class="btn btn--add">Voeg vakantie toe</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div style="margin-top:12px;">
                    <?php
                    // Render list of holidays; expects $timeslot_holidays as an array of rows with id, start_date, start_time, end_date, end_time
                    $hlist = isset($timeslot_holidays) ? $timeslot_holidays : [];
                    ?>
                    <div class="settings-card" style="margin-bottom:14px;">
                        <h4 style="margin:0 0 8px 0;font-size:1.1rem;color:#0b3d91;">Vakantieperiodes</h4>
                        <?php if (!empty($hlist)): ?>
                            <table class="table" style="width:100%;text-align:center;margin-top:8px;">
                                <thead>
                                    <tr>
                                        <th style="text-align:center;width:22%">Start datum</th>
                                        <th style="text-align:center;width:18%">Start tijd</th>
                                        <th style="text-align:center;width:22%">Eind datum</th>
                                        <th style="text-align:center;width:18%">Eind tijd</th>
                                        <th style="text-align:center;width:10%"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hlist as $h): ?>
                                        <?php
                                        // Format dates (Y-m-d -> d-m-Y) and times (H:i:s -> GuMM like 6u00)
                                        $start_date_raw = $h['start_date'] ?? '';
                                        $end_date_raw = $h['end_date'] ?? '';
                                        $start_time_raw = $h['start_time'] ?? '';
                                        $end_time_raw = $h['end_time'] ?? '';

                                        $start_date_fmt = $start_date_raw;
                                        $end_date_fmt = $end_date_raw;
                                        $start_time_fmt = $start_time_raw;
                                        $end_time_fmt = $end_time_raw;

                                        $d = DateTime::createFromFormat('Y-m-d', $start_date_raw);
                                        if ($d) $start_date_fmt = $d->format('d-m-Y');
                                        $d2 = DateTime::createFromFormat('Y-m-d', $end_date_raw);
                                        if ($d2) $end_date_fmt = $d2->format('d-m-Y');

                                        $t = DateTime::createFromFormat('H:i:s', $start_time_raw) ?: DateTime::createFromFormat('H:i', $start_time_raw);
                                        if ($t) $start_time_fmt = $t->format('G') . 'u' . $t->format('i');
                                        $t2 = DateTime::createFromFormat('H:i:s', $end_time_raw) ?: DateTime::createFromFormat('H:i', $end_time_raw);
                                        if ($t2) $end_time_fmt = $t2->format('G') . 'u' . $t2->format('i');
                                        ?>
                                        <tr>
                                            <td><?= htmlspecialchars($start_date_fmt) ?></td>
                                            <td><?= htmlspecialchars($start_time_fmt) ?></td>
                                            <td><?= htmlspecialchars($end_date_fmt) ?></td>
                                            <td><?= htmlspecialchars($end_time_fmt) ?></td>
                                            <td style="text-align:center;">
                                                <button class="ajax-delete" data-id="<?= (int)$h['id'] ?>" title="Verwijder" style="background:transparent;border:none;color:#c0392b;font-weight:bold;font-size:18px;line-height:1;cursor:pointer;">&times;</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="muted">Geen vakantieperiodes ingesteld</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- inner tabs now controlled server-side via $active_sub -->

    </div> <!-- end inner-tabs -->

    <!-- end tijdsloten include; the script that follows in the parent file remains unchanged -->
</div>
<script>
    (function() {
        // Holiday modal open/close
        var openBtn = document.getElementById('open_add_holiday');
        var modal = document.getElementById('addHolidayModal');
        var backdrop = document.getElementById('holidayModalBackdrop');
        var closeBtn = document.getElementById('close_add_holiday');
        var cancelBtn = document.getElementById('holiday_modal_cancel');

        function showModal() {
            if (modal) modal.style.display = 'flex';
        }

        function hideModal() {
            if (modal) modal.style.display = 'none';
        }

        if (openBtn) openBtn.addEventListener('click', function(e) {
            e.preventDefault();
            showModal();
        });
        if (closeBtn) closeBtn.addEventListener('click', function(e) {
            e.preventDefault();
            hideModal();
        });
        if (cancelBtn) cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            hideModal();
        });
        if (backdrop) backdrop.addEventListener('click', function() {
            hideModal();
        });

        // Delegated AJAX delete handler for .ajax-delete buttons
        document.addEventListener('click', function(ev) {
            var target = ev.target;
            if (!target) return;
            var btn = target.closest && target.closest('.ajax-delete');
            if (!btn) return;
            // If a direct listener already handled this button, skip here to avoid double-processing
            if (btn.dataset.deleteHandled) return;
            ev.preventDefault();

            var id = btn.getAttribute('data-id');
            if (!id) return;

            // Determine whether this delete is for holidays by checking table headers
            var isHoliday = false;
            var tr = btn.closest('tr');
            if (tr) {
                var table = tr.closest('table');
                if (table) {
                    var ths = table.querySelectorAll('thead th');
                    ths.forEach(function(th) {
                        var txt = (th.textContent || '').toLowerCase();
                        if (txt.indexOf('start datum') !== -1 || txt.indexOf('eind datum') !== -1) isHoliday = true;
                    });
                }
            }

            var data = new FormData();
            if (isHoliday) {
                data.append('delete_holiday_ajax', '1');
                data.append('holiday_id', id);
            } else {
                data.append('delete_range_ajax', '1');
                data.append('range_id', id);
            }

            fetch('/admin/instellingen', {
                method: 'POST',
                body: data,
                credentials: 'same-origin'
            }).then(function(resp) {
                return resp.json();
            }).then(function(json) {
                if (json && json.ok) {
                    if (tr) {
                        tr.style.transition = 'opacity 200ms ease';
                        tr.style.opacity = 0;
                        setTimeout(function() {
                            tr.parentNode && tr.parentNode.removeChild(tr);
                        }, 220);
                    } else {
                        window.location.reload();
                    }
                } else {
                    alert('Verwijderen mislukt');
                }
            }).catch(function() {
                alert('Verwijderen mislukt');
            });
        });
    })();
</script>
<script>
    // Direct listeners for .ajax-delete as a reliability fallback (no debug logging)
    (function() {
        function handleDeleteClick(ev) {
            ev.preventDefault();
            ev.stopPropagation();
            var btn = this;
            if (btn.dataset.deleteHandled) return;
            var id = btn.getAttribute('data-id');
            if (!id) return;
            // mark as handled to prevent duplicate handling
            btn.dataset.deleteHandled = '1';

            var tr = btn.closest('tr');
            var isHoliday = false;
            if (tr) {
                var table = tr.closest('table');
                if (table) {
                    var ths = table.querySelectorAll('thead th');
                    ths.forEach(function(th) {
                        var txt = (th.textContent || '').toLowerCase();
                        if (txt.indexOf('start datum') !== -1 || txt.indexOf('eind datum') !== -1) isHoliday = true;
                    });
                }
            }

            var data = new FormData();
            if (isHoliday) {
                data.append('delete_holiday_ajax', '1');
                data.append('holiday_id', id);
            } else {
                data.append('delete_range_ajax', '1');
                data.append('range_id', id);
            }

            fetch('/admin/instellingen', {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin'
                })
                .then(function(resp) {
                    return resp.json();
                })
                .then(function(json) {
                    if (json && json.ok) {
                        if (tr) {
                            tr.style.transition = 'opacity 200ms ease';
                            tr.style.opacity = 0;
                            setTimeout(function() {
                                tr.parentNode && tr.parentNode.removeChild(tr);
                            }, 220);
                        } else {
                            window.location.reload();
                        }
                    } else {
                        alert('Verwijderen mislukt');
                        delete btn.dataset.deleteHandled;
                    }
                }).catch(function() {
                    alert('Verwijderen mislukt');
                    delete btn.dataset.deleteHandled;
                });
        }

        document.querySelectorAll('.ajax-delete').forEach(function(b) {
            b.removeEventListener('click', handleDeleteClick);
            b.addEventListener('click', handleDeleteClick);
        });
    })();
</script>