/* autocompletion handler for quick edit */
// noinspection SpellCheckingInspection

jQuery(async function ($) {

  const theList = $('#the-list')
  let searchTerm
  const splitter = new RegExp('\\s+')

  function completeTheLabel (target, id) {
    const dataset = target.dataset
    const endpoint = `${dataset.url}/wp-json/wp/v2/users?context=edit&_fields=id,name,username&_locale=user`
    const search = '&include=' + id
    const url =  endpoint + search
    $.ajax(
        {
          url: url,
          dataType: 'json',
          type: 'get',
          beforeSend: function (xhr) {
            xhr.setRequestHeader('X-WP-Nonce', dataset.nonce);
          },
          success: function (data) {
            if (data.length === 1)  {
              item = data[0]
              const label = item.name + ' (' + item.username + ')'
              target.dataset.label = label
              target.dataset.p2 = label
              target.setAttribute('placeholder', label)

            }
          }
        }
    )
  }

  function setSelected(selectElement, id, label) {
    /* No options here? Weird. But just put one so we can use it. */
    if (selectElement.options.length < 1) {
      selectElement.appendChild(new HTMLOptionElement())
    }

    const option = selectElement.options[0];
    option.innerText = label;
    option.value = id

    /* deselect all except first option */
    let first = true
    for (const option of selectElement.options) {
      if (first) {
        option.setAttribute('selected', '')
        first = false
      } else {
        option.removeAttribute('selected')
      }
    }
    selectElement.selectedIndex = 0
  }

  const sleep = milliseconds => new Promise(r => setTimeout(r, milliseconds))

  /**
   * Sort the labels so items where the search term starts a name come first.
   * @param a
   * @param b
   * @returns {number}
   */
  function wordMatchesFirst(a, b) {
    const termStartsA = a.label.split(splitter).some(word => word.toLowerCase().startsWith(searchTerm.toLowerCase()))
    const termStartsB = b.label.split(splitter).some(word => word.toLowerCase().startsWith(searchTerm.toLowerCase()))
    if (termStartsA === termStartsB) {
      return a.label.localeCompare(b.label)
    }
    return termStartsA ? -1 : 1
  }

  /* parameters for REST query, from input.dataset */
  let dataset

  function fetch(req, res) {
    const endpoint = `${dataset.url}/wp-json/wp/v2/users?context=edit&per_page=${dataset.count}&_fields=id,name,username&_locale=user`
    const capabilities = typeof dataset.capabilities === 'string' ? '&capabilities=' + dataset.capabilities : '&who=authors'
    searchTerm = req.term
    const search = `&search=${req.term}`
    $.ajax(
      {
        url: endpoint + capabilities + search,
        dataType: 'json',
        type: 'get',
        beforeSend: function (xhr) {
          xhr.setRequestHeader('X-WP-Nonce', dataset.nonce);
        },
        success: function (data) {
          if (data.length === 0) {
            res(data);
          } else {
            const list = $.map(data, item => {
              const tag = item.name + ' (' + item.username + ')'
              return {label: tag, value: tag, id: item.id}
            })
            list.sort(wordMatchesFirst)
            res(list)
          }
        }
      }
    )
  }

  async function bulkClickHandler(event) {
    await clickHandler(event, 'bulk')
  }

  async function inlineClickHandler(event) {
    await clickHandler(event, 'inline')
  }

  /* Get an event when the user clicks quick-edit or bulk-edit
   * and defer handling the event, using setTimeout, until the next time
   * through the Javascript main loop. That's because we may get our
   * event before wp-admin/js/inline-edit-post.js line 127 gets theirs,
   * and they insert the quick-edit code into the DOM, copying it from
   * #inline-edit.   */
  async function clickHandler(event, type) {
    await sleep(0);
    const select = $('tr.inline-edit-row label.inline-edit-author select.index-wp-users-for-speed', theList)
    await autocompleteSetup(select[0])
  }

  /* Get an event when the user clicks quick-edit or bulk-edit
   * and defer handling the event, using setTimeout, until the next time
   * through the Javascript main loop. That's because we may get our
   * event before wp-admin/js/inline-edit-post.js line 127 gets theirs,
   * and they insert the quick-edit code into the DOM, copying it from
   * #inline-edit.   */
  async function autocompleteSetup(selectElement) {
    const autoCompleteElement = selectElement.parentElement.querySelector('span.input-text-wrap > input')
    dataset = autoCompleteElement.dataset
    const autoComplete = $(autoCompleteElement)
    try {
      autoComplete.autocomplete(
        {
          delay: 500,
          appendTo: autoComplete.parent().parent().first(),
          source: fetch,
          create: function (event) {
            const target = event.target
            const selecteds = selectElement.selectedOptions
            if (selecteds.length === 1) {
              const selected = selecteds[0]
              const id = selected.value
              target.dataset.id = id
              let label = selected.label
              if (typeof label !== 'string' || label.length === 0) {
                try {
                  /* No label on the first option in the select (no name for the author).
                   * Go find the item being edited, then the item's author.
                   * Workaround for https://core.trac.wordpress.org/ticket/56819 */
                  let editElement = selectElement
                  while (editElement && !editElement.classList.contains('inline-edit-row')) {
                    editElement = editElement.parentNode
                  }
                  const id = editElement.id.split('-')[1]
                  label = $('#post-' + id + ' .author').text()
                } catch (_) {
                  label = target.dataset.p1
                }
              }
              target.dataset.label = label
              target.dataset.p2 = label
              setSelected(selectElement, id, label)
              target.setAttribute('placeholder', label)
              completeTheLabel (target, id)
            }
          },
          select:
            function (event, ui) {
              const chosen = ui.item
              setSelected(selectElement, chosen.id, chosen.label)
            }
        }
      )
      /* Handle the placeholder in the autocomplete field,
       * showing the current author, but switching to
       * "Type the name" instructions
       * when the field gets focus.
       */
      autoCompleteElement.addEventListener('focus', event => {
        event.target.setAttribute('placeholder', event.target.dataset.p1);
      })
      autoCompleteElement.addEventListener('blur', event => {
        event.target.setAttribute('placeholder', event.target.dataset.p2);
      })
    } catch (e) {
      console.error(e)
    }
  }

  /* classic editor author choice */
  let done = false
  while (!done) {
    const selectElement = document.querySelector('div#authordiv > div.inside > select.index-wp-users-for-speed')
    if (!selectElement) break
    const labelElement = selectElement.parentElement.querySelector('label')
    if (!labelElement) break
    const autocompleteElement = selectElement.parentElement.querySelector('span.input-text-wrap > input')
    const id = 'index-mysql-users-for-speed-input'
    autocompleteElement.setAttribute('id', id)
    labelElement.setAttribute('for', id)
    await autocompleteSetup(selectElement)
    done = true
  }


  /* quick edit */
  theList.on('click', '.editinline', inlineClickHandler)

  /* bulk edit */
  $('#doaction').on('click', function (event) {
    if (this.parentElement.querySelector('select').value === 'edit') {
      bulkClickHandler(event).then()
    }
  });

})
