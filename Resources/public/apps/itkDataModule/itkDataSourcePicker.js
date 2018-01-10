angular.module('itkDataModule').directive('itkDataSourcePicker', [
  '$timeout', '$http', function ($timeout, $http) {
    return {
      restrict: 'E',
      replace: true,
      scope: {
        slide: '=',
        close: '&'
      },
      link: function (scope, element, attrs) {
        scope.open = true;

        scope.closeTool = function () {
          $timeout(function() {
            scope.open = false;

            $timeout(scope.close, 600);
          });
        };

        scope.availableDatasources = [];

        $http.get('/api/itk_aarhus_data/available_functions').then(
          function success(response) {
            $timeout(function () {
              scope.availableDatasources = response.data;
            });
          }
        );

        scope.data_types = ['json', 'csv']

      },
      templateUrl: '/bundles/itkaarhusdata/apps/itkDataModule/itkDataSourcePicker.html'
    };
  }
]);
