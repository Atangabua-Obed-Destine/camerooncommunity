@props(['size' => 'default'])
@php
    $uid = 'rb' . substr(md5(uniqid()), 0, 6);
    $sizeClasses = match($size) {
        'sm' => 'w-[180px] h-[140px] sm:w-[220px] sm:h-[170px] md:w-[280px] md:h-[210px] lg:w-[340px] lg:h-[260px]',
        default => 'w-[250px] h-[190px] sm:w-[320px] sm:h-[240px] md:w-[420px] md:h-[320px] lg:w-[520px] lg:h-[380px] xl:w-[600px] xl:h-[440px]',
    };
@endphp
<div class="absolute top-0 right-0 z-0 pointer-events-none {{ $sizeClasses }}"
     x-data
     x-init="
        $nextTick(() => {
            let u='{{ $uid }}',gp=$el.querySelector('#gP_'+u),rp=$el.querySelector('#rP_'+u),yp=$el.querySelector('#yP_'+u);
            if(!gp||!rp||!yp)return;
            let rL=rp.getTotalLength(),gL=gp.getTotalLength(),yL=yp.getTotalLength();
            [0.10,0.44,0.82].forEach((f,i)=>{
                let r=rp.getPointAtLength(rL*f),bG=1e9,bY=1e9,cG,cY;
                for(let j=0;j<=500;j++){
                    let g=gp.getPointAtLength(gL*j/500),y=yp.getPointAtLength(yL*j/500);
                    let dg=Math.hypot(g.x-r.x,g.y-r.y),dy=Math.hypot(y.x-r.x,y.y-r.y);
                    if(dg<bG){bG=dg;cG=g}if(dy<bY){bY=dy;cY=y}
                }
                let s=$el.querySelector('#s'+i+'_'+u);
                if(s)s.setAttribute('transform','translate('+((cG.x+cY.x)/2).toFixed(1)+','+((cG.y+cY.y)/2).toFixed(1)+')');
            });
        })
     ">
    <svg viewBox="0 0 600 440" fill="none" xmlns="http://www.w3.org/2000/svg"
         class="w-full h-full" preserveAspectRatio="xMaxYMin meet" style="pointer-events:none">
        <defs>
            <filter id="rSh_{{ $uid }}" x="-10%" y="-10%" width="120%" height="120%">
                <feDropShadow dx="2" dy="3" stdDeviation="4" flood-color="#000" flood-opacity="0.15"/>
            </filter>
        </defs>

        <g filter="url(#rSh_{{ $uid }})">
            <path id="gP_{{ $uid }}" d="M 80,-10 C 110,20 140,60 170,100 C 200,140 260,140 310,90 C 350,45 390,45 420,90 C 450,135 480,135 515,90 C 550,45 580,65 610,110"
                  stroke="#009639" stroke-width="20" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            <path id="rP_{{ $uid }}" d="M 100,-10 C 130,20 160,60 190,100 C 220,140 280,140 330,90 C 370,45 410,45 440,90 C 470,135 500,135 535,90 C 570,45 600,65 630,110"
                  stroke="#CE1126" stroke-width="20" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
            <path id="yP_{{ $uid }}" d="M 120,-10 C 150,20 180,60 210,100 C 240,140 300,140 350,90 C 390,45 430,45 460,90 C 490,135 520,135 555,90 C 590,45 620,65 650,110"
                  stroke="#FCD116" stroke-width="20" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
        </g>

        <g id="s0_{{ $uid }}"><polygon points="0,-8 1.9,-2.6 7.6,-2.6 2.9,1 4.7,6 0,2.3 -4.7,6 -2.9,1 -7.6,-2.6 -1.9,-2.6" fill="#FCD116" stroke="#CE1126" stroke-width="0.5"/></g>
        <g id="s1_{{ $uid }}"><polygon points="0,-8 1.9,-2.6 7.6,-2.6 2.9,1 4.7,6 0,2.3 -4.7,6 -2.9,1 -7.6,-2.6 -1.9,-2.6" fill="#FCD116" stroke="#CE1126" stroke-width="0.5"/></g>
        <g id="s2_{{ $uid }}"><polygon points="0,-8 1.9,-2.6 7.6,-2.6 2.9,1 4.7,6 0,2.3 -4.7,6 -2.9,1 -7.6,-2.6 -1.9,-2.6" fill="#FCD116" stroke="#CE1126" stroke-width="0.5"/></g>
    </svg>
</div>
