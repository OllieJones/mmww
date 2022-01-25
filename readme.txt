=== MMWW ===
Contributors: olliejones
Tags: mp3, images, metadata, exif, id3, iptc
Requires at least: 3.0.1
Tested up to: 5.9
Stable tag: 1.0.7
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: mmww
Author URI: https://github.com/OllieJones
Plugin URI: https://plumislandmedia.net/index-wp-mysql-for-speed/
Github Plugin URI: https://github.com/OllieJones/mmww/
Primary Branch: main

Media Metadata Workflow Wizard: Integrate your media metadata workflow with WordPress's Media Library

== To Do ==

* Figure out how to put keywords into a taxonomy if that's what the user wants.
* Add support for the Yet Another Photo Blog (YAPB) [plugin]{https://wordpress.org/extend/plugins/yet-another-photoblog/}
* Improve the syntax of the templates.
* Add support for aac and video file metadata.
* Ask for and receive lots of sample files from users, and use them to test.
* Come up with a better way to handle commas in metadata when generating audio shortcodes.
* Figure out a taxonomy to handle the media ratings in XMP.
* Support TIFF files. (Please let the author know if you need TIFF support.)

== Description ==

If you create lots of images, graphics, audio clips, or video clips  you probably go to some trouble to put metadata (titles, copyrights, track names, dates, and all that) into them. You may also put tags and ratings (one to five stars) on your media files.

JPEG image files have EXIF metadata. MP3 audio clips have ID3 metadata. PNG files have their own kind of metadata. Adobe is pushing an interoperable standard called XMP to hold metadata as well. Video files also have metadata. If you use a production tool like Acrobat, Adobe Bridge or Audacity, you probably put this kind of metadata into your files. And then you probably rekey it when you put the files into your WordPress site.

This Media Metadata Workflow Wizard plugin will get you out of doing that. Now you can have that metadata transferred into the Media Library automatically when you upload your media.

You can choose to have the creation date in your media file used as the "Uploaded" date in WordPress. So, for example, your photos can be ordered in the media library in
order of the date and time they were taken, and your pdfs in the order they were scanned.

You can specify templates defining what metadata items should be used to create each WordPress attachment post's fields: title, caption, alt text, and description.

For audio files, MMWW can automatically create the [audio] shortcode provided by [Jetpack](https://wordpress.org/extend/plugins/jetpack/).
Choose Link To Media File and the shortcode will be generated for you. WordPress 3.6 and later has an integrated audio player, so you may not need this feature. The Settings page lets you turn this behavior off.

If you use the [Media Tags][https://wordpress.org/plugins/media-tags/] plugin together with this one, you'll be able to handle metadata tags as a taxonomy. You can also use metadata ratings (one to five stars) as a taxonomy.


== Installation ==

Install and activate this plugin in the usual way.

== Metadata templates ==

Once the plugin is installed and activated, it will populate the text fields in your site's attachment
posts using metadata from the media files you upload. 

= Text fields for attachments =

The attachment text files are Title, Description, Caption and Alternate Text.  The Title of the attachment is also used to create the slug.

* The Description is free text describing the media item.
* The Caption is displayed underneath photos in posts.
* The Alternate Text is embedded in the IMG tag in the post. It serves two purposes: describing the image in textual form
for people who use screen readers because they cannot see the images, and for describing the image to search engines.

= Using metadata templates =

Many media editor programs, such as Photoshop, Paint Shop Pro, Acrobat and Audacity have ways of loading metadata into media.  These usually can be found in a dialog box named "Properties," "Image Information," or something similar.

MMWW's settings page lets you specify the templates to use for populating the text fields. For example, you can set the Description template for an image file from a smartphone to

    ({description} )({shutter}--{fstop} )({latitude}/{longitude} )({created_time})
     
and you'll see some details about how, where, and when the photo was taken in your Description.

You can use parentheses to delimit optional parts of a metadata template string. For example, not all media files contain {copyright} metadata.  If you put this into your metadata template string, it will omit the whole copyright clause if there's no {copyright} metadata. Notice that there's a trailing
space before the closing parenthesis.  This separates this clause (if it appears) from the next one.

      (Copyright &copy; {copyright} )

The parentheses denote the whole clause as optional, and omitted if the metadata mentioned in it is not available.

Similarly, you can create a URL that will display a map centered on the spot your photo was taken, but only if latitude and longitude are available in the photo's metadata, like this:

     (<A href="https://maps.google.com/?ll={latitude},{longitude}&z=18" target="_blank">\(Map {title}\)</A>)

If you want literal parentheses or curly braces to appear in your metadata, use the backslash character to escape them in your template data.

= JPEG image files =

JPEG photo files have lots of possible metadata. Not every photograph has all these items of metadata, but most have some of them.

     {title}           Title of the file.
     {filename}        Filename of the file. e.g. "DSC_5007" (without .jpg)
     {credit}          Author.
     {copyright}       Copyright notice if any is included.
     {description}     Narrative description.
     {tags}            One or more keyword tags, separated by semicolons.
     {rating}          0 - 5, set by various image browsers
     {workflowlabel}   A text string like "Discard" or "Keep," set by various image browsers
     {camera}          Camera model
     {shutter}         Shutter speed, such as 1/60
     {shutter_speed}   Raw shutter speed, such as 60
     {fstop}           Aperture, like f/5.6
     {aperture}        Raw aperture, like 5.60
     {flash}           Flash setting, such as "No Flash" or "Fired, Red-eye reduction"
     {focal_length}    Lens's focal length in mm.
     {focal_length35}  Lens's 35mm film focal length equivalent in mm.
     {lightsource}     Kind of light detected, such as "Daylight" or "Tungsten"
     {meteringmode}    Type of metering the camera used, such as "Spot," "Average," or "Unknown"
     {sensingmethod}   Type of image sensor, such as "One-chip color area sensor."
     {exposuremode}    Exposure mode, such as "Auto" or "Manual"
     {exposureprogram} Exposure-setting program, such as "Aperture Priority" or "Normal Program."
     {exposurebias}    Selected exposure bias.
     {brightness}      A number indicating how bright the scene is
     {scene_capture_type}  Scene capture type. Standard, Landscape, Portrait, Night
     {sharpness}       Image's sharpness.  Normal, Soft, Hard
     {latitude}        GPS latitude reading, shown in degrees and decimals.
     {longitude}       GPS longitude reading, showin in degrees and decimals.
     {altitude}        GPS altitude in meters above sea level
     {direction}       Direction of photograph. 270M means magnetic west, 180T means true south.
     {subject_distance} Distance to subject from autofocus or other means.
     {created_time}    Timestamp when the photograph was taken.

= IPTC metadata in JPEG image files =

The International Press Telecommunications Council has defined many items of metadata to go in photo files.  This metadata helps photojournalists and publications do business efficiently. Various tools, such as Adobe Bridge, allow this metadata to be inserted.  MMWW can retrieve it, with these tags

      {iptc:creator}              The creator's name.
      {iptc:creator:jobtitle}     The creator's job title.
      {iptc:creator:address}      The creator's address.
      {iptc:creator:city}         The creator's city.
      {iptc:creator:state}        The creator's state or province.
      {iptc:creator:postcode}     The creator's post / zip code.
      {iptc:creator:country}      The creator's country.
      {iptc:creator:phone}        The creator's phone(s).
      {iptc:creator:email}        The creator's email(s).
      {iptc:creator:website}      The creator's web site(s).
      {iptc:headline}             Headline.
      {iptc:description}          Description.
      {iptc:keywords}             Keywords, separated with comma or semicolon.
      {iptc:iptcsubjectcode}      IPTC subject code.
      {iptc:descriptionwriter}    Author of the description.
      {iptc:datecreated}          Creation date.
      {iptc:genre}                Intellectual genre.
      {iptc:scenecode}            IPTC scene code.
      {iptc:datecreated}          Creation date.
      {iptc:sublocation}          Location within city.
      {iptc:city}                 City.
      {iptc:state}                State/Province.
      {iptc:country}              Country.
      {iptc:iscocountrycode}      Country code per ISO 3166.
      {iptc:title}                Title.
      {iptc:jobidentifier}        Job Identifier.
      {iptc:instructions}         Instructions.
      {iptc:creditline}           Credit line.
      {iptc:source}               Source.
      {iptc:copyright}            Copyright Notice.
      {iptc:copyrightstatus}      Copyright Status.
      {iptc:rightsusageterms}     Terms of usage.


= PNG image files =

PNG image files have a few items of metadata. Most creators of PNG files don't know this metadata exists so it isn't often present.

     {title}               Title of the file.
     {credit}              Author.
     {copyright}           Copyright notice if any is included.
     {description}         Narrative description.
     {created_time}        The timestamp describing the time the PNG was made.
     {filename}            Filename of the file. e.g. "icon" (without .png)

= PDF =

PDF files, created by Adobe Acrobat and other programs, have a few items of metadata.  The most generally useful of these are the title and credit.

     {title}               Title of the file.
     {credit}              Author.
     {copyright}           Copyright notice if any is included.
     {description}         Narrative description.
     {tags}                One or more keyword tags, separated by semicolons.
     {rating}              0 - 5 
     {created_time}        The timestamp describing the time the PDF was made.
     {software}            Program used to create PDF.
     {filename}            Filename of the file. e.g. "scan1234" (without .pdf)


= Audio =

MP3 Audio files can have lots of metadata, defined by the ID3 standard.  The first few items are by far more common than the others.

     {title}               Title of the song.
     {album}               Title of the album.
     {credit}              Author or performer.
     {year}                Year of recording
     {copyright}           Copyright notice if any is included.
     {description}         Narrative description.
     {rating}              0 - 5
     {filename}            Filename of the file. e.g. "TRACK_003" (without .mp3)


These metadata items are in the ID3 standard for MP3 files, but most files don't have them.  MMWW handles them
in case your particular media workflow needs them.

	 {tempo}
	 {genre}
	 {grouptitle}
	 {keysignature}
	 {DDMM}              Day and month of recording
	 {HHMM}              Hour and minute of recording
	 {duration}
	 {creditlead}
	 {creditconductor}
     {creditproducer}
     {writer}
     {creditorganization}
     {mediatype}
     {creditoriginal}
     {copyright}

= Wordpress information for all files =

     {wp:attachmentid} gives the post id of the present post.
     {wp:parentid} gives the post id of the post to which this media file is attached.
     {wp:parenttitle} gives the title of the post to which this media file is attached.
     {wp:parentslug} gives the slug (url fragment) of the post to which this media file is attached.

These may be missing if those values are unknown at the time of media creation. Reloading the media metadata will
insert them.

= A note about timestamps =

MMWW has a setting that allows attachment dates to be set using the timestamp in the media's metadata. For example, the upload date for a photo can be set to the moment the photo was taken. That way, photos in your Media Library tab will appear in the order they were taken (if that's what you want).

There's a detail to this:  The timestamps in the media files need to be interpreted relative to a time zone to make this work correctly.  Consider the example of a photo taken in September in New York City and uploaded in November.  The timestamp in the photo is recorded in Eastern Daylight Time, but the current timezone setting is Eastern Standard Time. MMWW does the right thing by interpreting the photo's timestamp relative to the timezone chosen on WordPress's General Settings page. If you're getting strange times of day in your attachment dates, please check that the timezone setting on the General Settings page is correct.

= Metadata Standards Reference =

* [Adobe XMP](https://www.adobe.com/products/xmp/)
* [ID3 for MP3 files](https://id3.org/)
* [The ID3 Popularimeter](https://en.wikipedia.org/wiki/ID3#ID3v2_Rating_tag_issue) -- music file ratings
* [EXIF for JPEG files](https://www.exif.org/)
* [IPTC Photo Metadata](https://www.iptc.org/site/Photo_Metadata/)

== Frequently Asked Questions ==

= Do you have video file support? =

Not yet, but it is planned. Please let the author know if you're interested.

= Your plugin didn't read my media file correctly. What do I do now? =

Please send me the file at olliejones@gmail.com. By sending it to me you give me permission to add it to my test suite, and I'll do my best to get it working.

= If I upload a TIFF, my Insert Media dialog box stops working correctly.  Why?

It's a problem with WordPress, not with MMWW: WordPress doesn't handle TIFFs correctly. To fix your Insert Media dialog box, visit the Media Library from your dashboard, and delete all your TIFF attachments.


== Changelog ==

= 0.9.3 =

 1. Metadata extraction working for jpg, png, mp3, pdf files. (There's no metadata in gif files, and tiff files aren't supported by WordPress).
 1. Integration with the V3.5 media manager is complete.
 1. Automatic [audio] shortcode insertion working both pre- and post- WordPress 3.5.
 1. The Settings page allows specification of templates for populating attachment-post fields.
 
 = 0.9.4 =
 
 1. Add support for file ratings set by Windows Explorer and media player.

 = 1.0.0 =

Add a PDF filter to the media manager.

Add the reread-metadata function to the media manager.

Add better metadata string templates. You can use parentheses to delimit optional parts of a metadata template string. For example, not all media files contain {copyright} metadata.  If you put this into your metadata template string, it will omit the whole copyright clause if there's no {copyright}.

      (Copyright &copy; {copyright} )

The parentheses denote the whole clause as optional, and omitted if the metadata isn't available. Similarly, you can create a URL that will display a map, but only if latitude and longitude are available, like this:

     (<A href="https://maps.google.com/?ll={latitude},{longitude}&z=18" target="_blank">Map {title}</A>)

 = 1.0.1 =

* Jetpack's carousel plugin uses the {aperture} and {shutter_speed} items for photos, so retain them. Document already-existing {iso} and {focal_length} items.
* Add {focal_length35} item for focal length in 35mm sensor size equivalent
* Add {altitude} from GPS information.
* Add {direction} from GPS information. 270M means magnetic west, 180T means true south.
* Add {scene_capture_type}, {sharpness}, {subject_distance} and {exposurebias}

 = 1.0.2 =

A minor upgrade; Captures metadata correctly from PDFs containing multiple XMP chunks, thanks to Kevin Fraser for finding this bug.

 = 1.0.3 =

A minor upgrade;
* Add a {filename} tag for workflows where the filename is needed in the post.
* Handle some non-ASCII trouble in titles, content, and tags.
Thanks to Tony van der Voort for reporting these problems.

 = 1.0.4 =

Upgrade to WP 3.8.  Add more complete support for IPTC metadata, using {iptc:name} tags.

 = 1.0.5 =

Upgrade to WP 3.8.1  Repair defect with exif's {iso} tag. Add Spanish and Serbian translations thanks to Ognjen Djuraskovic.

Tests clean with WP 3.9.

 = 1.0.6 =

* Upgrade to WP 4.2
* Add "reread metadata" link to grid view of media per https://core.trac.wordpress.org/ticket/29056
* Add WordPress-specific tags
      {wp:attachmentid}, {wp:parentid}, {wp:parenttitle}, {wp:parentslug}

Tests clean with WP 4.1.1

== Upgrade Notice ==

 = 1.0.6 =
Upgrade to WP 4.1.1  Add "reread metadata" link to grid view of media.

== Credits ==

The stuff the US NSA is collecting isn't really metadata: it's call detail records. This stuff is metadata. Metadata can be poetry.

Ognjen Djuraskovic of [firstsiteguide.com](https://www.firstsiteguide.com/) has generously provided Serbian and Spanish translations of MMWW. Хвала / Gracias / Thanks Ognjen!

This plugin incorporates the Zend Media Framework by Sven Vollbehr and Ryan Butterfield which they generously made available under the BSD license. It comes in handy for retrieving
and decoding the ID3 tags from audio files. See the LICENSE.txt file in this distribution.

Copyright (c) 2005-2009 Zend Technologies USA Inc. (https://www.zend.com) Thanks, Sven and Ryan!
