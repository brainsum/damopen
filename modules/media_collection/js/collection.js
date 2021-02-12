/**
 * @file
 * Contains media collection code.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';
  Drupal.behaviors.media_collection = {
    attach: function (context, settings) {

      var removeIdentifier, removeIdentifierClass, checkbox = $('#watermark');

      $(document).ready(function () {
        if (document.colectionLoaded) {
          return;
        }

        if (settings.path.currentPath.split("/")[1] !== "shared") {
          $(".remove-icon-collection-list-hidden").removeClass("remove-icon-collection-list-hidden");
        }

        document.colectionLoaded = true;
        checkIdentifier(settings);
        $('#watermark').on('change', function () {
          checkIdentifier(settings);
          var activeControls = $(".image-controls .active");
          activeControls.click();
          var identifier = activeControls.attr("identifier");

          if (activeControls.parent().hasClass("inactive")) {
            $(".image-controls .controller:not(.inactive):eq(0) .link").click()
          }
        });

        var token = '';
        $(".button--add-to-collection").hide();

        changeCollectionHeader(settings.media_collection.items_in_collection.length);

        $.get('/session/token', function (sessionToken) {
          token = sessionToken
        }).done(function () {
          $(".button--add-to-collection").show();
          changeToAdded($(".in-collection"))
        });

        $(document).on("click", ".button--add-to-collection", function () {
          $(this).removeClass("button--add-to-collection").addClass("disabled");
          var that = this;
          var styleUuid = '';
          var topControls = $(".top-controls .active:last span");

          if (topControls.attr("data-style-uuid") === undefined) {
            styleUuid = settings.media_collection.default_image_style_uuid
          }
          else {
            styleUuid = topControls.attr("data-style-uuid")
          }
          var mediaType = $(this).attr("data-media-type");
          var mediaUuid = $(this).attr("data-media-uuid");

          addToCollection(mediaUuid, mediaType, styleUuid, token).done(function (res) {
            settings.media_collection.items_in_collection.push({
              collectionItemId: res.data.id,
              mediaId: mediaUuid,
              mediaType: mediaType,
              styleId: styleUuid
            });

            changeToAdded($(that));
            $(".image-controls .active").addClass("in-collection");
            var identifier = $(".image-controls .active").attr('identifier');
            if (!checkbox.is(':checked')) {
              $(".image-controls .active").addClass('no-badge');
            }
            $(that).attr("data-collection-item-uuid", res.data.id);
            changeCollectionHeader(parseInt($(".collection-item-number").text()) + 1);
          });
        });

        $(document).on("click", ".button--remove-style-from-collection", function () {
          var identifier = $(this).parent().attr("identifier");
          if ($(this).parent().hasClass('no-badge')) {
            identifier = identifier + '-no-badge';
            $(this).parent().removeClass('no-badge');
          }
          var that = this;
          changeCollectionHeader(parseInt($(".collection-item-number").text()) - 1);

          var selectedButton = $(".top-controls [identifier='" + identifier + "']:eq(1) span");
          var collectionItemUUID = selectedButton.attr("data-collection-item-uuid");

          var styleUuid = '';
          var topControls = $(".top-controls .active:last span");

          if (topControls.attr("data-style-uuid") === undefined) {
            styleUuid = settings.media_collection.default_image_style_uuid;
          }
          else {
            styleUuid = topControls.attr("data-style-uuid")
          }
          var mediaUuid = selectedButton.attr("data-media-uuid");

          settings.media_collection.items_in_collection.forEach(function (data, index) {
            if (data.collectionItemId === collectionItemUUID) {
              delete settings.media_collection.items_in_collection[index];
            }
          });

          removeFromCollection(collectionItemUUID, token).done(function (res) {
            changeToAdd($(".top-controls [identifier='" + identifier + "']:eq(1) span"));
            $(".top-controls .active:eq(1) span").removeClass("in-collection").removeClass("disabled");
            $(that).parents(".link").removeClass("in-collection");
          });
        });

        $(document).on("click", ".button--remove-in-collection", function () {
          var that = this;
          var collectionItemUUID = $(this).parents(".media_collection_item").data("collection-item-uuid");
          removeFromCollection(collectionItemUUID, token).done(function (res) {
            $(that).parents(".field--item").remove();
          })
        });

        function changeToAdded(tag) {

          tag = tag.removeClass("button--add-to-collection")
          // .addClass("in-collection")
          .attr("title", "Already added to your collection");

          if (!tag.find("img").hasClass("plus")) {
            tag.find("img").attr("src", "/modules/custom/media_collection/assets/added-to-collection.png")
          }
        }

        function changeToAdd(tag) {
          tag.addClass("button--add-to-collection")
          .attr("title", "Add to collection")
          .removeClass("disabled")
        }

        function checkIdentifier(settings) {
          $(".image-controls .in-collection").removeClass("in-collection");
          settings.media_collection.items_in_collection.map(function (media, index) {
            var button = $('*[data-media-uuid="' + media.mediaId + '"][data-style-uuid="' + media.styleId + '"]');

            if (button.length > 0) {
              button.addClass("in-collection").addClass("disabled");
              var identifier = button.parent().attr("identifier");

              // $(".image-controls div[identifier='" + identifier + "']").addClass("in-collection");
              if (identifier.indexOf('no-badge') > 0) {
                $(".image-controls div[identifier='" + identifier + "']").addClass('no-badge');
              }
            }
          })
        }


        function changeCollectionHeader(count) {
          var collectionCount = $(".collection-item-number");

          if (count > parseInt(collectionCount.text())) {
            $(".collection-header").addClass("zoom-width");
            setTimeout(function () {
              $(".collection-header").removeClass("zoom-width")
            }, 2100)
          }
          if (count > 0) {
            collectionCount.text(count);
            $(".collection-header-empty").hide();
            $(".collection-header").show()
          }
          else {
            $(".collection-header").hide();
            $(".collection-header-empty").show()
          }
        }

        function addToCollection(mediaUuid, mediaType, styleUuid, token) {
          var style = {};
          if (mediaType === "image") {
            style = {
              "data": {
                "type": "image_style--image_style",
                "id": styleUuid
              }
            }
          }

          return $.ajax({
            url: '/jsonapi/media_collection_item/media_collection_item',
            type: 'post',
            data: JSON.stringify({
              "data": {
                "type": "media_collection_item--media_collection_item",
                "attributes": {},
                "relationships": {
                  "media": {
                    "data": {
                      "type": "media--" + mediaType,
                      "id": mediaUuid
                    }
                  },
                  "style": style
                }
              }
            }),
            headers: {
              "X-CSRF-Token": token,
              "Content-Type": "application/vnd.api+json",
              "Accept": "application/vnd.api+json"
            },
            dataType: 'json'
          })
        }

        function removeFromCollection(collectionItemUUID, token) {
          return $.ajax({
            url: '/jsonapi/media_collection_item/media_collection_item/' + collectionItemUUID,
            type: 'delete',
            headers: {
              "Content-Type": "application/vnd.api+json",
              "Accept": "application/vnd.api+json",
              "X-CSRF-Token": token
            },
            dataType: 'json'
          })
        }


        $(".card").hover(function () {
          $(this).addClass("text-overlay-hover")
        }, function () {
          $(this).removeClass("text-overlay-hover")
        });

        $(".shared_media_collection .icon-help, .shared_media_collection .dam-media-description-title").on("click", function () {
          if ($(this).hasClass("visible")) {
            $(".useage-overlay-wrapper").removeClass("visible")
          }
          else {
            $(".useage-overlay-wrapper").addClass("visible")
          }
        });

        function showRemoveLink() {
          $('.image-controls').find('.link').removeClass('in-collection');
          $('.top-controls').find('.link').each(function(index, item) {
            $(item).find('span').each(function(key, value) {
              if ($(value).hasClass('in-collection')) {
                removeIdentifier = $(value).closest('div').attr('identifier');
                if (!checkbox.is(':checked')) {
                  if (removeIdentifier.indexOf('no-badge') > 0) {
                    $('.image-controls .link').removeClass('in-collection');
                    removeIdentifierClass = removeIdentifier.replace('-no-badge', '');
                    $('.image-controls .link[identifier="' + removeIdentifierClass + '"]').addClass('in-collection');
                    $('.image-controls .link[identifier="' + removeIdentifierClass + '"]').addClass('no-badge');
                  }
                }
                else {
                  $('.image-controls .link[identifier="' + removeIdentifier + '"]').addClass('in-collection');
                  $('.image-controls .link[identifier="' + removeIdentifier + '"]').removeClass('no-badge');
                }
              }
            });
          });
        }
        showRemoveLink();
        checkbox.on('change', function(){
          showRemoveLink();
        });
      });

    }
  };

})(jQuery, Drupal, drupalSettings);
