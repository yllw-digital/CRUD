@php
    $currentGraphId = time().'_'.str_random(10);
@endphp
<div class="{{ $widget['wrapperClass'] ?? 'col-sm-6 col-md-4' }} chart-wrapper">
	<canvas id="{{ $currentGraphId }}"></canvas>
</div>

@push('after_scripts')

<script type="text/javascript">
var config = {
			type: 'line',
			data: {
				labels: ['January', 'February', 'March', 'April', 'May', 'June', 'July'],
				datasets: [{
					label: 'My First dataset',
					backgroundColor: 'rgba(255,255,255,.1)',
					borderColor: 'rgba(255,255,255,.1)',
					data: [
						1,2,3,4,5,6,7
					],
					fill: false,
				}]
			},
			options: {
				responsive: true,
				title: {
					display: true,
					text: 'Chart.js Line Chart'
				},
				tooltips: {
					mode: 'index',
					intersect: false,
				},
				hover: {
					mode: 'nearest',
					intersect: true
				},
				scales: {
					xAxes: [{
						display: true,
						scaleLabel: {
							display: true,
							labelString: 'Month'
						}
					}],
					yAxes: [{
						display: true,
						scaleLabel: {
							display: true,
							labelString: 'Value'
						}
					}]
				}
			}
		};

window.onload = function() {

    var ctx = document.getElementById('{{ $currentGraphId }}').getContext('2d');
    window.myLine = new Chart(ctx, config);
};
</script>
@endpush
