/* Admin: selector media (logo, imagine deal) și repeater FAQ. Doar în admin. */
(function () {
  'use strict';

  document.addEventListener('click', function (e) {
    var t = e.target;

    var pick = t.closest('[data-cupons-media-pick]');
    if (pick && window.wp && wp.media) {
      e.preventDefault();
      var box = pick.closest('[data-cupons-media]');
      var frame = wp.media({ title: 'Alege imaginea', library: { type: 'image' }, multiple: false });
      frame.on('select', function () {
        var a = frame.state().get('selection').first().toJSON();
        var img = box.querySelector('img');
        box.querySelector('input[type=hidden]').value = a.id;
        img.src = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);
        img.hidden = false;
        box.querySelector('[data-cupons-media-clear]').hidden = false;
      });
      frame.open();
      return;
    }

    var clear = t.closest('[data-cupons-media-clear]');
    if (clear) {
      e.preventDefault();
      var b = clear.closest('[data-cupons-media]');
      b.querySelector('input[type=hidden]').value = '';
      b.querySelector('img').hidden = true;
      clear.hidden = true;
      return;
    }

    var add = t.closest('[data-cupons-add]');
    if (add) {
      e.preventDefault();
      var rep = add.closest('[data-cupons-repeater]');
      var tpl = rep.querySelector('template');
      add.before(tpl.content.cloneNode(true));
      return;
    }

    var rm = t.closest('[data-cupons-remove]');
    if (rm) {
      e.preventDefault();
      rm.closest('.cupons-faq__row').remove();
    }
  });

  // Formularul „Adaugă magazin” (AJAX) nu reîncarcă pagina: golim câmpurile noastre după salvare.
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('addtag');
    if (!form || !window.jQuery) return;
    jQuery(document).ajaxComplete(function (ev, xhr, settings) {
      if (!settings.data || settings.data.indexOf('action=add-tag') === -1) return;
      form.querySelectorAll('[data-cupons-media] input[type=hidden]').forEach(function (i) { i.value = ''; });
      form.querySelectorAll('[data-cupons-media] img').forEach(function (i) { i.hidden = true; });
      form.querySelectorAll('input[name^="cupons[cats]"]').forEach(function (i) { i.checked = false; });
    });
  });
})();
