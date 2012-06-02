/*
Plugin: 3D Tag Sphere
Version: 0.1
Author: Ian George
Website: http://www.iangeorge.net
Tools: Emacs, js2-mode
Tested on: IE6, IE7, IE8, Firefox 3.6 Linux, Firefox 3.5 Windows, Chrome Linux / Windows
Requirements: Optional jquery.mousewheel for zooming

Description: 3d tag cloud, rotates with the mouse and zooms in and out. Fixed camera position to simplify calculations

Known issues:
 Breaks badly on IE5.5
 Looks a bit rough on page load

TODO:
 Performance is horrible when more than one instance is present in the page
 Would be much quicker if instead of recalculating all values on mouse move a global phi/theta value pair is stored and the values calculated on-the-fly in Draw()

Options:
  Option            default            Comments
  --------------------------------------------------
  zoom              75                 Initial zoom level
  min_zoom          25        
  max_zoom          120      
  zoom_factor       () 2               Speed of zoom by the mouse wheel
  rotate_by         -1.75              In degrees, the amount that the sphere rotates. Negative values reverse the direction.
  fps               10                 Defines the (target) number of times the animation will be updated per second
  centrex           250                Horizontal rotation centre in the container <div>
  centrey           250                Vertical rotation centre in the container <div>
  min_font_size     12
  max_font_size     32
  font_units        'px'
  random_points     50                 Adds some random points on to the sphere to enhance the effect
  foreground_colour #fff               Accepted colour formats are #333 #0A0A0A and rgb(n,n,n)
  background_colour rgb(0,0,0)         Named colours may not work

Usage:
 Vanilla:
    $('.tags').tagcloud();

 Centered in a 200 x 200 container:
    $('.tags').tagcloud({centrex:100,centrey:100});

 With a different update speed
    $('.selector').tagcloud({fps:24});

Markup:
 Must be an unordered list in a div with links in the list items. 
 rel="[number]" is optional but necessary for ranking by font-size.
 <div class="tags">
 <ul>
   <li><a href="#" rel="20">link 1</a></li>
   <li><a href="#" rel="20">link 2</a></li>
   <li><a href="#" rel="20">link 3</a></li>
   <li><a href="#" rel="20">link 4</a></li>
   <li><a href="#" rel="20">link 5</a></li>
 </ul>
 */

(function($){

     // jquery plugin hook
     $.fn.tagcloud = function(options){
        
         // overwrite defaults with user-specified
         var opts = $.extend($.fn.tagcloud.defaults, options);
         opts.drawing_interval = 1/(opts.fps/1000);
         
         //create a new class for every matching element
         $(this).each(function(){
                          $('ul', $(this)).css('display', 'none');
                          new TagCloudClass($(this), opts);
                      });
         return this;         
     };


     //default values for setup
     $.fn.tagcloud.defaults = {
         zoom: 90,
         max_zoom: 120,
         min_zoom: 25,
         zoom_factor: 2, //multiplication factor for wheel delta
         rotate_factor: -0.45, // multiplication factor for rotation
         fps: 10, // frames per second
         centrex: 250, // set centre of display
         centrey: 250,
         min_font_size: 12, //font limits and units
         max_font_size: 32,
         font_units: 'px',
         //uncomment to override the background-color and color css props from the container
         //background_colour: '#000',
         //foreground_colour: 'rgb(255,255,255)',         
         random_points: 0         
     };

     var TagCloudClass = function(el, options){
         $(el).css('position', 'relative');

         // general values
         var eyez = -500;

         // set rotation (in this case, 5degrees)
         var rad = Math.PI/180;
         var global_cos = Math.cos(0);

         var hex = new Array("0","1","2","3","4","5","6","7","8","9","a","b","c","d","e","f");

         // per-instance values
         var dirty = true;
         var container = $(el);
         var id_stub = 'tc_' + $(el).attr('id') + "_";
         var opts = options;
         var zoom = opts.zoom;
         var depth;
         var lastx = 0;
         var lasty = 0;
	 var points = [];
         points['data'] = [];
         var drawing_interval;
         var cmx = options.centrex; 
         var cmy = options.centrey;
         var bg_colour, fg_colour;
         if (options.background_colour){
             bg_colour = parsecolour(options.background_colour);
         }else{
             bg_colour = parsecolour($(el).css('background-color'));
         }
         if (options.foreground_colour){
             fg_colour = parsecolour(options.foreground_colour);
         }else{
             fg_colour = parsecolour($(el).css('color'));
         }
         
         function parsecolour(colour){
             function parse_rgb_colour(colour){
                 rgb = colour.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/);
                 if(rgb.length > 3){
                     return {"r" : parseInt(rgb[1]), "g": parseInt(rgb[2]), "b" : parseInt(rgb[3])};                 
                 }else{  
                     return {"r":0, "g":0, "b":0};
                 }
             }
             function parse_hex_colour(colour){
                 var r = 0, g = 0, b = 0;
                 if(colour.length > 4)
                 {
                     r = parseInt(colour.substr(1,2), 16);
                     g = parseInt(colour.substr(3,2), 16);
                     b = parseInt(colour.substr(5,2), 16);
                 }
                 else
                 {
                     r = parseInt(colour.substr(1,1)+colour.substr(1,1), 16);
                     g = parseInt(colour.substr(2,1)+colour.substr(2,1), 16);
                     b = parseInt(colour.substr(3,1)+colour.substr(3,1), 16);
                 }
                 return {"r" : r, "g" : g, "b" : b};
             }

             if(colour.substr(0, 1) === '#')
             {
                 return parse_hex_colour(colour);
             }
             else if (colour.substr(0,3) === 'rgb')
             {
                 return parse_rgb_colour(colour);
             }
             else{
                 //somehow we've got a plain old string as a colour
                 if(window.console != undefined)
                     console.log("unable to parse:'" + colour + "' please ensure background and foreground colors for the container are set as hex values");
                 return null;
             }
         }
             
         function getcolour(num){
             if(num>256){num=256;}
             if(num<0){num=0;}

             var r = getshade(bg_colour.r, fg_colour.r, num);
             var g = getshade(bg_colour.g, fg_colour.g, num);
             var b = getshade(bg_colour.b, fg_colour.b, num);

             var ret  = "rgb(" + r + ", "+ g + ", " + b + ")"; 
             return ret;
         }

         function getshade(lbound, ubound, dist){
             var scope = ubound - lbound;
             var dist_percent = scope / 100;
             var shade = Math.round(lbound + (dist * dist_percent));
             return shade;
         }

         
         //drawing and rotation...				
         function rotx(s, c){
             for(var p in points.data)
             {
	         var temp = s * points.data[p].y + c * points.data[p].z;
	         points.data[p].y = c * points.data[p].y - s * points.data[p].z;
	         points.data[p].z = temp;
             }
         }

         function roty(s, c){
             for(var p in points.data){
	         var temp = - s * points.data[p].x + c * points.data[p].z;
	         points.data[p].x = c * points.data[p].x + s * points.data[p].z;
	         points.data[p].z = temp; 
             }
         }

         function rotz(s, c){
             for(var p in points.data) {
	         var temp = s * points.data[p].x + c * points.data[p].y;
	         points.data[p].x = c * points.data[p].x - s * points.data[p].y;
	         points.data[p].y = temp;
             }
         }

         function zoomed(by){
             zoom += by * opts.zoom_factor;

             if (zoom > opts.max_zoom) {
	         zoom = opts.max_zoom;
             }
             if (zoom < opts.min_zoom) {
	         zoom = opts.min_zoom;
             }

             depth = -(zoom * (eyez - opts.max_zoom) / 100) + eyez;
         }

         function moved(mx,my){
             if(mx>lastx){
                 var angle = mx - lastx;
	         var sin = -Math.sin(angle*options.rotate_factor*rad);
                 var cos = Math.cos(angle*options.rotate_factor*rad);
	         roty(sin, cos);
             }
             if(mx<lastx){
                 var angle = lastx - mx;
	         var sin = Math.sin(angle*options.rotate_factor*rad);
                 var cos = Math.cos(angle*options.rotate_factor*rad);
	         roty(sin, cos);			
             }
             if(my>lasty){
                 var angle = my - lasty;
	         var sin = Math.sin(angle*options.rotate_factor*rad);
                 var cos = Math.cos(angle*options.rotate_factor*rad);
	         rotx(sin, cos);
             }
             if(my<lasty){
                 var angle = lasty - my;
	         var sin = -Math.sin(angle*options.rotate_factor*rad);
                 var cos = Math.cos(angle*options.rotate_factor*rad);
	         rotx(sin, cos);
             }

             lastx = mx;
             lasty = my;
             dirty = true;
         }

         function draw(){
             // calculate 2D coordinates
             if(dirty){
                 var smallz = 10000; var bigz = -10000;
                 for(var r_p in points.data){
	             if(points.data[r_p].z < smallz){smallz = points.data[r_p].z;}
	             if(points.data[r_p].z > bigz){bigz = points.data[r_p].z;}
                 }
                 var minz = Math.min(smallz, bigz);
                 var maxz = Math.max(smallz, bigz);
                 var diffz = maxz - minz;
                 
                 for(var s_p in points.data){ 
                     //normalise depth
	             var u = (depth - eyez)/(points.data[s_p].z - eyez);

                     // calculate normalised grey value
                     var dist = Math.round(((maxz - points.data[s_p].z)/diffz) * 100);
                     var dist_colour = getcolour(dist);
                     //set new 2d positions for the data
                     $('#'+points.data[s_p].id + ' a', container).css('color',dist_colour);
                     $('#'+points.data[s_p].id, container).css('z-index',dist);
                     $('#'+points.data[s_p].id, container).css('left', u * points.data[s_p].x + cmx - points.data[s_p].cwidth);
                     $('#'+points.data[s_p].id, container).css('top', u * points.data[s_p].y + cmy); 
                 }			
                 dirty = false;
             }
         }

         // number of elements we're adding and placeholders for range values
         points.count = $('li a', container).length;
         points.largest = 1;
         points.smallest = 0;


         // Run through each li > a in the container and create an absolutely-positioned div in its place
         // Also need to create a data structure to keep state between calls to draw()
         // Data structure is as follows:
         // 
         // points{
         //     'count':0,                      //Total number of points
         //     'largest':0,                    //largest 'size' value
         //     'smallest':0,                   //Smallest 'size' value
         //     'data':{
         //         'id':"",                    //HTML id for element
         //         'size':0,                   //Size (from rel attribute on <a>)
         //         'theta':0.0,                //Angle on sphere (used to calculate initial cartesian position)
         //         'phi': 0.0,                 //Angle on sphere (used to calculate initial cartesian position)
         //         'x':0.0,                    //Cartesian position in 3d space
         //         'y':0.0,                    //Cartesian position in 3d space
         //         'z':0.0,                    //Cartesian position in 3d space
         //     }
         // }
         $('li a', container).each(function(idx, val){

                                       var sz = parseInt($(this).attr('rel'));
                                       if(sz == 0) 
                                           sz = 1;

                                       points.data[idx] = {
                                           id:id_stub + idx,
                                           size:sz
                                       };

                                       // plot the points on a sphere
                                       // from: http://www.math.niu.edu/~rusin/known-math/97/spherefaq
                                       // for k=1 to N do
                                       //     h = -1 + 2*(k-1)/(N-1)
                                       //     theta[k] = arccos(h)
                                       //     if k=1 or k=N then phi[k] = 0
                                       //     else phi[k] = (phi[k-1] + 3.6/sqrt(N*(1-h^2))) mod (2*pi)
                                       // endfor

                                       // In Cartesian coordinates the required point on a sphere of radius 1 is
                                       // (cos(theta)*sin(phi), sin(theta)*sin(phi), cos(phi))
                                       
                                       var h = -1 + 2*(idx)/(points.count-1);
                                       points.data[idx].theta = Math.acos(h);
                                       if(idx == 0 || idx == points.count-1){
                                           points.data[idx].phi = 0;
                                       }
                                       else{
                                           points.data[idx].phi = (points.data[idx-1].phi + 3.6/Math.sqrt(points.count*(1-Math.pow(h,2)))) % (2 * Math.PI);
                                       }

                                       points.data[idx].x = Math.cos(points.data[idx].phi) * Math.sin(points.data[idx].theta) * (cmx/2);
                                       points.data[idx].y = Math.sin(points.data[idx].phi) * Math.sin(points.data[idx].theta) * (cmy/2);
                                       points.data[idx].z = Math.cos(points.data[idx].theta) * (cmx/2);

                                       if(sz > points.largest) points.largest = sz;
                                       if(sz < points.smallest) points.smallest = sz;

                                       container.append('<div id="'+ id_stub + idx +'" class="point" style="position:absolute;"><a href=' + $(this).attr('href')  + '>' + $(this).html()  + '</a></div>');
                                   });

         //if required to do so (by opts.random_points being > 0) we need to generate some random points on the sphere
         //bit cheezy, but can make more sparse data sets look a bit more believable
         if(opts.random_points > 0){
             for(b=0; b<opts.random_points; b++){
                 points.count++;                                                  
                 points.data[points.count] = {
                     id:id_stub + points.count,
                     size:1
                 };
                 points.data[points.count].theta = Math.random() * 2 * Math.PI;
                 points.data[points.count].phi = Math.random() * 2 * Math.PI;
                 points.data[points.count].x = Math.cos(points.data[points.count].phi) * Math.sin(points.data[points.count].theta) * (cmx/2);
                 points.data[points.count].y = Math.sin(points.data[points.count].phi) * Math.sin(points.data[points.count].theta) * (cmy/2);
                 points.data[points.count].z = Math.cos(points.data[points.count].theta) * (cmx/2);
                 container.append('<div id="'+ id_stub + points.count +'" class="point" style="position:absolute;"><a>.</a></div>');
             }
         }
         
         //tag size and font size ranges 
         var sz_range = points.largest - points.smallest + 1; 
         var sz_n_range = opts.max_font_size - opts.min_font_size + 1;
         
         //set font size to normalised tag size
         for(var p in points.data){
             var sz = points.data[p].size;
             var sz_n = parseInt((sz / sz_range) * sz_n_range) + opts.min_font_size;
             if(!$('#' + points.data[p].id, container).hasClass('background')){
                 $('#' + points.data[p].id, container).css('font-size', sz_n); 
             }
             //store element width / 2 so we can centre the text around the point later.
             points.data[p].cwidth = $('#' + points.data[p].id, container).width()/2;
         }
         // bin original html
         $('ul', container).remove();

         //set up initial view
         depth = -(zoom * (eyez - opts.max_zoom) / 100) + eyez;
         moved(cmx, cmy);

         //call draw every so often
         drawing_interval = setInterval(draw, opts.drawing_interval);

         //events to change position of items
         container.mousemove(function(evt){
                                 moved(evt.clientX, evt.clientY);
                             });
         container.mousewheel(function(evt, delta){
                                  zoomed(delta);
                                  evt.preventDefault();
                                  return false;
                              });
         
     };
          
 })(jQuery);
