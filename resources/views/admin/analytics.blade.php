@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header"><div>
        <h1 class="fw-bold mb-1">Analytics</h1>
        <p class="text-muted mb-0">A live view of bookings, confirmed revenue, and client activity.</p>
    </div></div>
    
    <div class="row g-4 mb-4">
        <div class="col-md-4 col-sm-6">
            <div class="stat-card p-4 h-100">
                <div class="badge-soft mb-2">Reservations</div>
                <h3 class="fw-bold">{{ $monthlyReservations->sum('total') }}</h3>
                <p class="mb-0 text-muted">Bookings in the past 12 months</p>
            </div>
        </div>
        <div class="col-md-4 col-sm-6">
            <div class="stat-card p-4 h-100">
                <div class="badge-soft mb-2">Confirmed revenue</div>
                <h3 class="fw-bold">&#8369;{{ number_format($monthlyRevenue->sum('revenue'), 0) }}</h3>
                <p class="mb-0 text-muted">Fully paid completed events</p>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="stat-card p-4 h-100">
                <div class="badge-soft mb-2">Top package</div>
                <h3 class="fw-bold" style="font-size:1.3rem">{{ str($topPackages->first()?->name ?? 'No bookings yet')->limit(20) }}</h3>
                <p class="mb-0 text-muted">Most requested package</p>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card p-4 h-100">
                <h4 class="fw-semibold mb-3">Monthly Reservations</h4>
                <canvas id="reservationsChart" style="max-height:300px;"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card p-4 h-100">
                <h4 class="fw-semibold mb-3">Fully Paid Revenue</h4>
                <canvas id="revenueChart" style="max-height:300px;"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card p-4 h-100">
                <h4 class="fw-semibold mb-3">Package Popularity</h4>
                <canvas id="packageChart" style="max-height:300px;"></canvas>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card p-4 h-100">
                <h4 class="fw-semibold mb-3">Inquiry Activity</h4>
                <canvas id="activityChart" style="max-height:300px;"></canvas>
            </div>
        </div>
    </div>
    
    <div class="card p-4 mt-4">
        <h4 class="fw-semibold mb-3">Top Packages</h4>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Package</th>
                        <th class="text-end">Bookings</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topPackages as $package)
                        <tr>
                            <td>{{ $package->name }}</td>
                            <td class="text-end"><strong>{{ $package->total }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="2" class="text-center text-muted py-3">No package bookings yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels=@json($monthlyReservations->pluck('label')), 
    reservationData=@json($monthlyReservations->pluck('total')), 
    revenueData=@json($monthlyRevenue->pluck('revenue')), 
    packageLabels=@json($topPackages->pluck('name')), 
    packageData=@json($topPackages->pluck('total')), 
    activityLabels=@json($activityLabels), 
    activityData=@json($activityData); 

const chartText=getComputedStyle(document.body).color;
const base={
    responsive:true,
    maintainAspectRatio:true,
    plugins:{legend:{labels:{color:chartText}}},
    scales:{x:{ticks:{color:chartText}},y:{beginAtZero:true,ticks:{color:chartText}}}
};

new Chart(document.getElementById('reservationsChart'),{
    type:'bar',
    data:{labels,datasets:[{data:reservationData,backgroundColor:'#b66545',borderRadius:5}]},
    options:{...base,plugins:{legend:{display:false}}}
});

new Chart(document.getElementById('revenueChart'),{
    type:'line',
    data:{labels,datasets:[{data:revenueData,borderColor:'#b66545',backgroundColor:'rgba(182,101,69,.18)',fill:true,tension:.35}]},
    options:{...base,plugins:{legend:{display:false}}}
});

new Chart(document.getElementById('packageChart'),{
    type:'doughnut',
    data:{labels:packageLabels,datasets:[{data:packageData,backgroundColor:['#6d3024','#b66545','#c7984b','#66727a']}]},
    options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{position:'bottom',labels:{color:chartText}}}}
});

new Chart(document.getElementById('activityChart'),{
    type:'line',
    data:{labels:activityLabels,datasets:[{label:'Inquiries',data:activityData,borderColor:'#c7984b',backgroundColor:'rgba(199,152,75,.18)',fill:true,tension:.35}]},
    options:base
});
</script>
@endsection
