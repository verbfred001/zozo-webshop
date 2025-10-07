// option-rule-overrides.js
(function (window, document) {
  'use strict';

  // Generic initializer.
  // config:
  //  root: Element root to query within (defaults to document)
  //  triggerGroupId: number
  //  triggerValues: array of strings (values that activate the rule)
  //  targetGroupIds: array of group ids to hide/make optional
  //  sentinelValue: string to send as hidden value (default '0')
  function initOptionRuleOverrides(config) {
    const root = config.root || document;
    const triggerGroupId = String(config.triggerGroupId);
    const triggerValues = (config.triggerValues || []).map(String);
    const targetGroupIds = (config.targetGroupIds || []).map(String);
    const sentinelValue = config.sentinelValue != null ? String(config.sentinelValue) : '0';
    const hiddenClass = 'override-hidden-input';

    // Helper: find wrapper for a group (adjust selector if your markup differs)
    function findWrapper(groupId) {
      // support both detail and modal markup
      return root.querySelector(`.detail-option-group[data-group-id="${groupId}"], .modal-option-group[data-group-id="${groupId}"]`);
    }

    // Hide or restore one target group
    function setTargetState(targetGroupId, hide) {
      const wrapper = findWrapper(targetGroupId);
      if (!wrapper) return;

      // collect inputs inside wrapper
      const fields = Array.from(wrapper.querySelectorAll('input,select,textarea'));

      if (hide) {
        // disable and remove required; mark previous state
        const nameSet = new Set();
        fields.forEach(f => {
          if (f.name) nameSet.add(f.name);
          // save previous state
          f.dataset._wasDisabled = f.disabled ? '1' : '0';
          f.dataset._wasRequired = f.hasAttribute('required') ? '1' : '0';

          // save original value/checked so we can restore later
          if (f.type === 'checkbox' || f.type === 'radio') {
            f.dataset._origChecked = f.checked ? '1' : '0';
            f.checked = false;
          } else {
            f.dataset._origValue = f.value != null ? String(f.value) : '';
            if (f.tagName && f.tagName.toLowerCase() === 'select') {
              // if sentinel exists as an option, select it; otherwise clear selection
              const hasSentinel = Array.from(f.options || []).some(o => String(o.value) === sentinelValue);
              if (hasSentinel) {
                try { f.value = sentinelValue; } catch (e) { f.selectedIndex = -1; }
              } else {
                try { f.selectedIndex = -1; } catch (e) { f.value = ''; }
              }
            } else {
              // clear text-like inputs to avoid their values contributing to price
              try { f.value = ''; } catch (e) { /* ignore */ }
            }
          }

          // finally disable and remove required
          f.disabled = true;
          f.removeAttribute('required');

          // trigger change so any price-listeners update
          try { f.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) { }
        });

        // add hidden inputs for every distinct field name
        nameSet.forEach(name => {
          const hid = document.createElement('input');
          hid.type = 'hidden';
          hid.name = name;
          hid.value = sentinelValue; // '0' by default, change if you prefer another sentinel
          hid.className = hiddenClass;
          wrapper.appendChild(hid);
        });

        wrapper.dataset._overrideHidden = '1';
        wrapper.style.display = 'none';
      } else {
        // remove hidden inputs
        wrapper.querySelectorAll('input.' + hiddenClass).forEach(h => h.remove());
        // restore fields
        fields.forEach(f => {
          // restore disabled/required
          f.disabled = f.dataset._wasDisabled === '1' ? true : false;
          if (f.dataset._wasRequired === '1') f.setAttribute('required', 'required');

          // restore original value/checked
          if (f.type === 'checkbox' || f.type === 'radio') {
            f.checked = f.dataset._origChecked === '1' ? true : false;
            delete f.dataset._origChecked;
          } else {
            if (typeof f.dataset._origValue !== 'undefined') {
              try { f.value = f.dataset._origValue; } catch (e) { }
              delete f.dataset._origValue;
            }
          }

          delete f.dataset._wasDisabled;
          delete f.dataset._wasRequired;

          // trigger change so price recalculation sees restored values
          try { f.dispatchEvent(new Event('change', { bubbles: true })); } catch (e) { }
        });
        delete wrapper.dataset._overrideHidden;
        wrapper.style.display = '';
      }
    }

    // apply rules depending on trigger value
    function applyRuleBasedOnTriggerValue(triggerValue) {
      const shouldHide = triggerValues.includes(String(triggerValue));
      targetGroupIds.forEach(tid => setTargetState(tid, shouldHide));
    }

    // find trigger element: either select[name="option_58"] or select[data-group-id="58"]
    const trigger = root.querySelector(`select[name="option_${triggerGroupId}"], select[data-group-id="${triggerGroupId}"]`);
    if (!trigger) return { mounted: false };

    // initial apply
    applyRuleBasedOnTriggerValue(trigger.value);

    // listen to future changes
    trigger.addEventListener('change', function () {
      applyRuleBasedOnTriggerValue(this.value);
    });

    return { mounted: true, trigger: trigger };
  }

  // expose to global
  window.initOptionRuleOverrides = initOptionRuleOverrides;
})(window, document);